<?php

namespace App\Services;

use App\Interfaces\ReceiptIssuerInterface;
use App\Models\DitronReceipt;
use App\Models\OrderItem;
use App\Models\PrecontoSplit;
use App\Models\Setting;
use App\Models\TableOrder;
use App\Support\IssuedReceiptDto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Emette scontrini fiscali via DitronAgent (HTTP) → WinEcrCom → cassa Ditron RT.
 *
 * Persistenza: tabella `ditron_receipts`.
 * Non tocca CorrispettivoService/Mysond. Attivabile via setting
 * `corrispettivo_provider = 'ditron'`.
 */
final class DitronReceiptService implements ReceiptIssuerInterface
{
    private const PAYMENT_METHODS_EXCLUDED = [
        'fattura',
        'fattura_contanti',
        'fattura_pos',
        'chiusura_conto',
    ];

    private const LOG_CHANNEL = 'corrispettivi';

    public function providerName(): string
    {
        return 'ditron';
    }

    public function emettiPerOrdine(TableOrder $order, string $paymentMethod, ?int $operatorId): ?IssuedReceiptDto
    {
        if (in_array($paymentMethod, self::PAYMENT_METHODS_EXCLUDED, true)) {
            $this->log('info', 'Emissione Ditron saltata: metodo escluso (fattura)', [
                'table_order_id' => $order->id,
                'payment_method' => $paymentMethod,
            ]);
            return null;
        }

        $payload = $this->buildPayloadForOrder($order, $paymentMethod);
        $importo = $order->hasDiscount() ? (float) $order->getDiscountedTotal() : (float) $order->total_amount;

        $receipt = DitronReceipt::create([
            'table_order_id'    => $order->id,
            'preconto_split_id' => null,
            'idempotency_key'   => $this->idempotencyKeyForOrder($order),
            'payment_method'    => $paymentMethod,
            'importo_totale'    => $importo,
            'status'            => DitronReceipt::STATUS_PENDING,
            'max_attempts'      => (int) Setting::get('corrispettivo_max_attempts', 3),
            'request_payload'   => $payload,
            'operator_id'       => $operatorId,
        ]);

        $this->log('info', 'Inizio emissione Ditron per ordine', $receipt->getLogContext() + [
            'importo' => (float) $receipt->importo_totale,
        ]);

        $this->dispatch($receipt, $payload);

        return IssuedReceiptDto::fromDitron($receipt->refresh());
    }

    public function emettiPerSplit(PrecontoSplit $split, string $paymentMethod, ?int $operatorId): ?IssuedReceiptDto
    {
        if (in_array($paymentMethod, self::PAYMENT_METHODS_EXCLUDED, true)) {
            $this->log('info', 'Emissione Ditron split saltata: metodo escluso (fattura)', [
                'preconto_split_id' => $split->id,
                'payment_method'    => $paymentMethod,
            ]);
            return null;
        }

        $payload = $this->buildPayloadForSplit($split, $paymentMethod);

        $receipt = DitronReceipt::create([
            'table_order_id'    => $split->table_order_id,
            'preconto_split_id' => $split->id,
            'idempotency_key'   => $this->idempotencyKeyForSplit($split),
            'payment_method'    => $paymentMethod,
            'importo_totale'    => (float) $split->total,
            'status'            => DitronReceipt::STATUS_PENDING,
            'max_attempts'      => (int) Setting::get('corrispettivo_max_attempts', 3),
            'request_payload'   => $payload,
            'operator_id'       => $operatorId,
        ]);

        $this->log('info', 'Inizio emissione Ditron per split', $receipt->getLogContext() + [
            'importo' => (float) $receipt->importo_totale,
        ]);

        $this->dispatch($receipt, $payload);

        return IssuedReceiptDto::fromDitron($receipt->refresh());
    }

    /**
     * Costruisce il payload JSON per POST /emit-receipt dell'agent.
     */
    private function buildPayloadForOrder(TableOrder $order, string $paymentMethod): array
    {
        $order->loadMissing(['items.dish', 'restaurantTable']);

        $items = $order->items
            ->filter(fn(OrderItem $i) => !$i->isSegueItem() && (float) $i->subtotal > 0)
            ->map(fn(OrderItem $i) => $this->itemToPayload($i))
            ->values()
            ->all();

        return [
            'idempotency_key'         => $this->idempotencyKeyForOrder($order),
            'table_number'            => $this->tableNumberForOrder($order),
            'covers'                  => (int) $order->covers,
            'cover_charge_unit_price' => $this->coverChargeUnitPrice($order),
            'items'                   => $items,
            'reparto'                 => (int) Setting::get('ditron_default_reparto', 1),
            'tender'                  => (int) Setting::get('ditron_default_tender', 5),
            'payment_method'          => $paymentMethod,
            'discount'                => $this->discountPayload($order),
        ];
    }

    private function discountPayload(TableOrder $order): ?array
    {
        if (!$order->hasDiscount()) {
            return null;
        }
        $value = round((float) $order->discount_value, 2);
        if ($value <= 0) {
            return null;
        }
        return [
            'value'       => $value,
            'description' => $this->discountDescription($order),
        ];
    }

    private function discountDescription(TableOrder $order): string
    {
        if ($order->discount_type === 'percent') {
            $percent = (float) $order->discount_amount;
            return 'SCONTO ' . rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.') . '%';
        }
        return 'SCONTO';
    }

    private function buildPayloadForSplit(PrecontoSplit $split, string $paymentMethod): array
    {
        $order = $split->order;
        $order?->loadMissing(['restaurantTable']);

        $items = [];
        foreach ((array) ($split->items ?? []) as $it) {
            $qty = max(1, (int) ($it['quantity'] ?? 1));
            $subtotal = (float) ($it['subtotal'] ?? 0);
            if ($subtotal <= 0) {
                continue;
            }
            $items[] = [
                'description' => (string) ($it['dish_name'] ?? 'ARTICOLO'),
                'unit_price'  => round($subtotal / $qty, 2),
                'quantity'    => (float) $qty,
            ];
        }

        // Split equi (type 'split'/'amounts') hanno items=null: emettiamo una riga unica con il totale.
        // Lo scontrino fiscale riporta "Pasto completo" per evitare descrizioni come "Preconto 3/3"
        // che non sono conformi sul Fiscal Ditron.
        if (empty($items)) {
            $items[] = [
                'description' => 'Pasto completo',
                'unit_price'  => round((float) $split->total, 2),
                'quantity'    => 1.0,
            ];
        }

        return [
            'idempotency_key'         => $this->idempotencyKeyForSplit($split),
            'table_number'            => $order ? $this->tableNumberForOrder($order) : null,
            'covers'                  => (int) $split->covers,
            'cover_charge_unit_price' => $split->covers > 0 && $order ? (float) $order->getCoverChargePerPerson() : null,
            'items'                   => $items,
            'reparto'                 => (int) Setting::get('ditron_default_reparto', 1),
            'tender'                  => (int) Setting::get('ditron_default_tender', 5),
            'payment_method'          => $paymentMethod,
        ];
    }

    private function itemToPayload(OrderItem $item): array
    {
        $description = (string) ($item->dish->print_label ?? $item->dish->label ?? $item->dish->name ?? 'ARTICOLO');
        $unitPrice = (float) $item->subtotal / max(1, (int) $item->quantity);

        return [
            'description' => $description,
            'unit_price'  => round($unitPrice, 2),
            'quantity'    => (float) $item->quantity,
        ];
    }

    private function tableNumberForOrder(TableOrder $order): ?int
    {
        $tn = $order->restaurantTable?->table_number;
        return $tn !== null ? (int) $tn : null;
    }

    private function coverChargeUnitPrice(TableOrder $order): ?float
    {
        if ((int) $order->covers <= 0) {
            return null;
        }
        return (float) $order->getCoverChargePerPerson();
    }

    private function idempotencyKeyForOrder(TableOrder $order): string
    {
        return 'table_order:' . $order->id . ':' . ($order->closed_at?->timestamp ?? now()->timestamp);
    }

    private function idempotencyKeyForSplit(PrecontoSplit $split): string
    {
        return 'preconto_split:' . $split->id . ':' . ($split->updated_at?->timestamp ?? now()->timestamp);
    }

    /**
     * Effettua la chiamata HTTP all'agent e aggiorna il record.
     */
    private function dispatch(DitronReceipt $receipt, array $payload): void
    {
        $baseUrl = (string) Setting::get('ditron_agent_url', '');
        if ($baseUrl === '') {
            $receipt->update([
                'status'     => DitronReceipt::STATUS_FAILED,
                'last_error' => 'ditron_agent_url non configurato',
                'attempts'   => $receipt->attempts + 1,
            ]);
            return;
        }

        $receipt->update([
            'status'    => DitronReceipt::STATUS_SENDING,
            'attempts'  => $receipt->attempts + 1,
            'agent_url' => $baseUrl,
        ]);

        $token = (string) Setting::get('ditron_agent_token', '');
        $timeout = (int) Setting::get('ditron_agent_timeout_seconds', 20);

        $request = Http::timeout($timeout)->acceptJson();
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->post(rtrim($baseUrl, '/') . '/emit-receipt', $payload);
        } catch (ConnectionException $e) {
            $receipt->update([
                'status'     => DitronReceipt::STATUS_FAILED,
                'last_error' => 'connect_error: ' . Str::limit($e->getMessage(), 250),
            ]);
            return;
        } catch (Throwable $e) {
            $receipt->update([
                'status'     => DitronReceipt::STATUS_FAILED,
                'last_error' => 'unexpected_error: ' . Str::limit($e->getMessage(), 250),
            ]);
            return;
        }

        $body = $response->json();
        $isOk = $response->successful() && (bool) ($body['ok'] ?? false);

        $receipt->update([
            'status'         => $isOk ? DitronReceipt::STATUS_SENT : DitronReceipt::STATUS_FAILED,
            'receipt_number' => $body['receipt_number'] ?? null,
            'raw_command'    => $body['raw_command'] ?? null,
            'raw_err'        => $body['raw_err'] ?? null,
            'elapsed_ms'     => $body['elapsed_ms'] ?? null,
            'last_error'     => $isOk ? null : ($body['error'] ?? ('http_' . $response->status())),
            'sent_at'        => $isOk ? now() : null,
        ]);

        $this->log($isOk ? 'info' : 'warning', $isOk ? 'Ditron emesso' : 'Ditron failed', $receipt->getLogContext() + [
            'status'     => $receipt->status,
            'elapsed_ms' => $receipt->elapsed_ms,
        ]);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(self::LOG_CHANNEL)->log($level, '[Ditron] ' . $message, $context);
    }
}
