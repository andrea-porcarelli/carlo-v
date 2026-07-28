<?php

namespace App\Services;

use App\Interfaces\ReceiptIssuerInterface;
use App\Models\DitronReceipt;
use App\Models\OrderItem;
use App\Models\PrecontoSplit;
use App\Models\Setting;
use App\Models\TableOrder;
use App\Models\User;
use App\Support\IssuedReceiptDto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
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
    /**
     * Whitelist esplicita: la ricevuta fiscale va emessa SOLO per un incasso effettivo
     * in contanti o pagamento elettronico (POS). Qualsiasi altro metodo (chiusura_conto,
     * fattura_*, bonifico, assegno, misto, ecc.) NON deve emettere lo scontrino.
     */
    private const PAYMENT_METHODS_ALLOWED = [
        'contanti',
        'pos',
    ];

    private const LOG_CHANNEL = 'corrispettivi';

    public function providerName(): string
    {
        return 'ditron';
    }

    public function emettiPerOrdine(TableOrder $order, string $paymentMethod, ?int $operatorId, ?string $keySuffix = null): ?IssuedReceiptDto
    {
        if (!in_array($paymentMethod, self::PAYMENT_METHODS_ALLOWED, true)) {
            $this->log('info', 'Emissione Ditron saltata: metodo non ammesso (solo contanti/pos)', [
                'table_order_id' => $order->id,
                'payment_method' => $paymentMethod,
            ]);
            return null;
        }
        if (!$this->isEnabled()) {
            $this->log('info', 'Emissione Ditron saltata: corrispettivi disabilitati da settings', [
                'table_order_id' => $order->id,
            ]);
            return null;
        }

        // La idempotency_key base è deterministica su (order_id, closed_at). Per emissioni
        // "extra" successive alla prima (es. riemissione manuale dopo DOCANNULLO o dopo un
        // fallimento) il chiamante deve passare un `keySuffix` univoco, altrimenti l'INSERT
        // fallirebbe sul vincolo UNIQUE della colonna idempotency_key.
        $key = $this->idempotencyKeyForOrder($order);
        if ($keySuffix !== null && $keySuffix !== '') {
            $key .= ':' . $keySuffix;
        }

        $payload = $this->buildPayloadForOrder($order, $paymentMethod);
        $payload['idempotency_key'] = $key;
        $importo = $order->hasDiscount() ? (float) $order->getDiscountedTotal() : (float) $order->total_amount;

        $receipt = DitronReceipt::create([
            'table_order_id'    => $order->id,
            'preconto_split_id' => null,
            'idempotency_key'   => $key,
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
        if (!in_array($paymentMethod, self::PAYMENT_METHODS_ALLOWED, true)) {
            $this->log('info', 'Emissione Ditron split saltata: metodo non ammesso (solo contanti/pos)', [
                'preconto_split_id' => $split->id,
                'payment_method'    => $paymentMethod,
            ]);
            return null;
        }
        if (!$this->isEnabled()) {
            $this->log('info', 'Emissione Ditron split saltata: corrispettivi disabilitati da settings', [
                'preconto_split_id' => $split->id,
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
            'tender'                  => $this->tenderForPaymentMethod($paymentMethod),
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

    /**
     * Analoga a discountPayload() ma per un singolo split preconto: gli split hanno
     * discount_type/amount/value propri (vedi TableOrderController::createPreconto).
     * Gli items dello split sono memorizzati al LORDO dello sconto, quindi senza questo
     * blocco lo scontrino chiuderebbe a importo pieno anziché netto.
     */
    private function discountPayloadForSplit(PrecontoSplit $split): ?array
    {
        if ($split->discount_type === null || $split->discount_type === 'none') {
            return null;
        }
        $value = round((float) $split->discount_value, 2);
        if ($value <= 0) {
            return null;
        }
        $description = $split->discount_type === 'percent'
            ? 'SCONTO ' . rtrim(rtrim(number_format((float) $split->discount_amount, 2, '.', ''), '0'), '.') . '%'
            : 'SCONTO';
        return [
            'value'       => $value,
            'description' => $description,
        ];
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

        // Il campo `discount` va inviato SOLO quando gli items sono al lordo dello sconto
        // (split "dettagliati" per-piatto). Per gli split equi/importi il fallback qui sopra
        // ha già collassato il netto nella riga "Pasto completo": ripetere lo sconto lo
        // scalerebbe due volte.
        $splitHasDetailedItems = !empty((array) ($split->items ?? []));
        $discount = $splitHasDetailedItems ? $this->discountPayloadForSplit($split) : null;

        return [
            'idempotency_key'         => $this->idempotencyKeyForSplit($split),
            'table_number'            => $order ? $this->tableNumberForOrder($order) : null,
            'covers'                  => (int) $split->covers,
            'cover_charge_unit_price' => $split->covers > 0 && $order ? (float) $order->getCoverChargePerPerson() : null,
            'items'                   => $items,
            'reparto'                 => (int) Setting::get('ditron_default_reparto', 1),
            'tender'                  => $this->tenderForPaymentMethod($paymentMethod),
            'payment_method'          => $paymentMethod,
            'discount'                => $discount,
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

    /**
     * Codice tender (T=N) usato nella chiusura fiscale della cassa Ditron RT.
     * Mappa il payment_method (whitelist contanti/pos) al setting dedicato.
     * Per metodi non ammessi ricadiamo sul default storico: emettiPer* filtra
     * comunque a monte, quindi questo fallback non dovrebbe mai essere raggiunto.
     */
    private function tenderForPaymentMethod(string $paymentMethod): int
    {
        return match ($paymentMethod) {
            'contanti' => (int) Setting::get('ditron_tender_contanti', 1),
            'pos'      => (int) Setting::get('ditron_tender_pos', 5),
            default    => (int) Setting::get('ditron_default_tender', 5),
        };
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

        // Recovery: WinEcrCom scrive nel .err warning transitori (es. EFPTR_WRONG_STATE=207)
        // anche quando lo scontrino È stato effettivamente stampato. Se il chiamante ha
        // registrato un raw_err ma l'agent ha comunque parlato con la cassa, interroghiamo
        // GETP per vedere se un nuovo fiscal_number è stato emesso: in tal caso promuoviamo
        // il record a sent con i dati fiscali reali.
        $recovered = null;
        if (!$isOk && !empty($body['raw_err'] ?? '')) {
            $recovered = $this->tryRecoverFromCash($receipt);
        }
        $effectiveOk = $isOk || $recovered !== null;

        $receipt->update([
            'status'         => $effectiveOk ? DitronReceipt::STATUS_SENT : DitronReceipt::STATUS_FAILED,
            'receipt_number' => $body['receipt_number'] ?? null,
            'fiscal_number'  => $recovered['fiscal_number'] ?? ($body['fiscal_number'] ?? null),
            'fiscal_date'    => $this->pickFiscalDate($recovered, $body),
            'z_number'       => $recovered['z_number'] ?? ($body['z_number'] ?? null),
            'matricola'      => $recovered['matricola'] ?? ($body['matricola'] ?? null),
            'raw_command'    => $body['raw_command'] ?? null,
            'raw_err'        => $body['raw_err'] ?? null,
            'elapsed_ms'     => $body['elapsed_ms'] ?? null,
            'last_error'     => $effectiveOk ? null : ($body['error'] ?? ('http_' . $response->status())),
            'sent_at'        => $effectiveOk ? now() : null,
        ]);

        $level = $effectiveOk ? 'info' : 'warning';
        $msg = match (true) {
            $isOk               => 'Ditron emesso',
            $recovered !== null => 'Ditron emesso (recovered via GETP)',
            default             => 'Ditron failed',
        };
        $this->log($level, $msg, $receipt->getLogContext() + [
            'status'     => $receipt->status,
            'elapsed_ms' => $receipt->elapsed_ms,
            'recovered'  => $recovered !== null,
        ]);
    }

    /**
     * Legge N proprietà GETP dalla cassa via agent. Ritorna array associativo
     * [ok, values, error, elapsed_ms, raw_command, raw_err] già decodificato.
     * Non tocca database — è una lettura pura.
     */
    public function readProperties(array $propertyNumbers): array
    {
        $baseUrl = (string) Setting::get('ditron_agent_url', '');
        if ($baseUrl === '') {
            return ['ok' => false, 'error' => 'ditron_agent_url non configurato', 'values' => []];
        }

        $token = (string) Setting::get('ditron_agent_token', '');
        $timeout = (int) Setting::get('ditron_agent_timeout_seconds', 20);

        $request = Http::timeout($timeout)->acceptJson();
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->post(rtrim($baseUrl, '/') . '/read-properties', [
                'properties' => array_values(array_map('intval', $propertyNumbers)),
            ]);
        } catch (ConnectionException $e) {
            return ['ok' => false, 'error' => 'connect_error: ' . Str::limit($e->getMessage(), 250), 'values' => []];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'unexpected_error: ' . Str::limit($e->getMessage(), 250), 'values' => []];
        }

        $body = $response->json() ?? [];
        $this->log('info', 'GETP letto da backoffice', [
            'properties' => $propertyNumbers,
            'http'       => $response->status(),
            'ok'         => $body['ok'] ?? false,
            'elapsed_ms' => $body['elapsed_ms'] ?? null,
        ]);

        return [
            'ok'          => (bool) ($body['ok'] ?? false),
            'error'       => $body['error'] ?? null,
            'values'      => (array) ($body['values'] ?? []),
            'raw_command' => $body['raw_command'] ?? null,
            'raw_err'     => $body['raw_err'] ?? null,
            'elapsed_ms'  => $body['elapsed_ms'] ?? null,
        ];
    }

    /**
     * Ritenta l'invio di uno scontrino di vendita marcato `failed`. Riusa il
     * `request_payload` originale ma genera una nuova `idempotency_key` (l'agent oggi
     * non depuplica, ma se in futuro lo farà evitiamo di collidere con il tentativo fallito).
     */
    public function retry(DitronReceipt $receipt): DitronReceipt
    {
        if ($receipt->isCancel()) {
            throw new RuntimeException("Scontrino #{$receipt->id} è un annullo: non ritentabile con questo flusso.");
        }
        if (!$receipt->canRetry()) {
            throw new RuntimeException(
                "Scontrino #{$receipt->id} non ritentabile: status={$receipt->status}, tentativi {$receipt->attempts}/{$receipt->max_attempts}."
            );
        }
        $payload = (array) $receipt->request_payload;
        if (empty($payload)) {
            throw new RuntimeException("Scontrino #{$receipt->id} privo di request_payload: retry impossibile.");
        }

        $newKey = $receipt->idempotency_key . ':r' . ($receipt->attempts + 1);
        $payload['idempotency_key'] = $newKey;

        $receipt->update([
            'idempotency_key' => $newKey,
            'last_error'      => null,
        ]);

        $this->log('info', 'Retry Ditron da backoffice', $receipt->getLogContext() + [
            'previous_error' => $receipt->last_error,
        ]);

        $this->dispatch($receipt, $payload);
        return $receipt->refresh();
    }

    /**
     * Emette un documento di annullamento (DOCANNULLO opcode 124) per uno scontrino
     * di vendita già emesso. Crea un nuovo record `ditron_receipts` con type=cancel,
     * chiama l'agent su /emit-cancel, e — a successo — marca la sale originale come
     * `cancelled_at`. Log completo raw_command + raw_err su entrambi i record.
     *
     * @throws RuntimeException se la sale non è annullabile.
     */
    public function emitCancel(DitronReceipt $sale, User $admin, string $reason): DitronReceipt
    {
        if (!$sale->isCancellable()) {
            throw new RuntimeException("Scontrino Ditron #{$sale->id} non è annullabile: " . $this->explainNotCancellable($sale));
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Motivazione obbligatoria per emettere un annullo Ditron.');
        }

        $payload = [
            'idempotency_key' => 'cancel:' . $sale->id . ':' . now()->timestamp,
            'fiscal_number'   => $sale->fiscal_number,
            'fiscal_date'     => optional($sale->fiscal_date)->toDateString(),
            'z_number'        => $sale->z_number,
            'matricola'       => $sale->matricola,
        ];

        $cancel = DitronReceipt::create([
            'table_order_id'     => $sale->table_order_id,
            'preconto_split_id'  => $sale->preconto_split_id,
            'idempotency_key'    => $payload['idempotency_key'],
            'type'               => DitronReceipt::TYPE_CANCEL,
            'cancels_receipt_id' => $sale->id,
            'cancel_reason'      => $reason,
            'payment_method'     => $sale->payment_method,
            'importo_totale'     => $sale->importo_totale,
            'status'             => DitronReceipt::STATUS_PENDING,
            'max_attempts'       => (int) Setting::get('corrispettivo_max_attempts', 3),
            'request_payload'    => $payload,
            'operator_id'        => $admin->id,
        ]);

        $this->log('info', 'Inizio emissione DOCANNULLO Ditron', $cancel->getLogContext() + [
            'sale_receipt_id' => $sale->id,
            'sale_fiscal'     => "{$sale->fiscal_number} del " . optional($sale->fiscal_date)->toDateString(),
            'admin_user_id'   => $admin->id,
            'reason'          => $reason,
        ]);

        $this->dispatchCancel($cancel, $payload);
        $cancel->refresh();

        if ($cancel->isSent()) {
            $sale->update([
                'cancelled_at'            => now(),
                'cancelled_by_receipt_id' => $cancel->id,
                'cancelled_by_user_id'    => $admin->id,
                'cancel_reason'           => $reason,
            ]);
        }

        return $cancel;
    }

    private function explainNotCancellable(DitronReceipt $sale): string
    {
        if (!$sale->isSale()) return 'non è uno scontrino di vendita';
        if (!$sale->isSent()) return "status={$sale->status}, ci si aspetta 'sent'";
        if ($sale->isCancelled()) return "già annullato il " . $sale->cancelled_at?->toDateTimeString();
        if (!filled($sale->fiscal_number)) return 'manca fiscal_number (non arrivato dalla cassa)';
        if (!filled($sale->fiscal_date)) return 'manca fiscal_date';
        if (!filled($sale->z_number)) return 'manca z_number';
        if (!filled($sale->matricola)) return 'manca matricola';
        return 'motivo sconosciuto';
    }

    private function dispatchCancel(DitronReceipt $cancel, array $payload): void
    {
        $baseUrl = (string) Setting::get('ditron_agent_url', '');
        if ($baseUrl === '') {
            $cancel->update([
                'status'     => DitronReceipt::STATUS_FAILED,
                'last_error' => 'ditron_agent_url non configurato',
                'attempts'   => $cancel->attempts + 1,
            ]);
            return;
        }

        $cancel->update([
            'status'    => DitronReceipt::STATUS_SENDING,
            'attempts'  => $cancel->attempts + 1,
            'agent_url' => $baseUrl,
        ]);

        $token = (string) Setting::get('ditron_agent_token', '');
        $timeout = (int) Setting::get('ditron_agent_timeout_seconds', 20);

        $request = Http::timeout($timeout)->acceptJson();
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->post(rtrim($baseUrl, '/') . '/emit-cancel', $payload);
        } catch (ConnectionException $e) {
            $cancel->update([
                'status'     => DitronReceipt::STATUS_FAILED,
                'last_error' => 'connect_error: ' . Str::limit($e->getMessage(), 250),
            ]);
            return;
        } catch (Throwable $e) {
            $cancel->update([
                'status'     => DitronReceipt::STATUS_FAILED,
                'last_error' => 'unexpected_error: ' . Str::limit($e->getMessage(), 250),
            ]);
            return;
        }

        $body = $response->json();
        $isOk = $response->successful() && (bool) ($body['ok'] ?? false);

        // Stessa logica di recovery via GETP applicata all'emissione: anche il DOCANNULLO
        // (opcode 124) può ricevere warning WinEcrCom pur avendo stampato correttamente.
        $recovered = null;
        if (!$isOk && !empty($body['raw_err'] ?? '')) {
            $recovered = $this->tryRecoverFromCash($cancel);
        }
        $effectiveOk = $isOk || $recovered !== null;

        $cancel->update([
            'status'         => $effectiveOk ? DitronReceipt::STATUS_SENT : DitronReceipt::STATUS_FAILED,
            'receipt_number' => $body['receipt_number'] ?? null,
            'fiscal_number'  => $recovered['fiscal_number'] ?? ($body['fiscal_number'] ?? null),
            'fiscal_date'    => $this->pickFiscalDate($recovered, $body),
            'z_number'       => $recovered['z_number'] ?? ($body['z_number'] ?? null),
            'matricola'      => $recovered['matricola'] ?? ($body['matricola'] ?? null),
            'raw_command'    => $body['raw_command'] ?? null,
            'raw_err'        => $body['raw_err'] ?? null,
            'elapsed_ms'     => $body['elapsed_ms'] ?? null,
            'last_error'     => $effectiveOk ? null : ($body['error'] ?? ('http_' . $response->status())),
            'sent_at'        => $effectiveOk ? now() : null,
        ]);

        $level = $effectiveOk ? 'info' : 'warning';
        $msg = match (true) {
            $isOk               => 'DOCANNULLO Ditron emesso',
            $recovered !== null => 'DOCANNULLO Ditron emesso (recovered via GETP)',
            default             => 'DOCANNULLO Ditron failed',
        };
        $this->log($level, $msg, $cancel->getLogContext() + [
            'status'     => $cancel->status,
            'elapsed_ms' => $cancel->elapsed_ms,
            'recovered'  => $recovered !== null,
        ]);
    }

    /**
     * Interroga la cassa via GETP (prop 1 matricola, 10 num ultimo scontrino, 12 Z/data)
     * per verificare se, nonostante l'agent abbia riportato errore, un nuovo documento
     * fiscale è stato effettivamente emesso. È il fix per i warning transitori di WinEcrCom
     * (es. EFPTR_WRONG_STATE=207) che appaiono nel .err ma non impediscono la stampa.
     *
     * Criterio: il fiscal_number letto dalla cassa deve essere strettamente maggiore
     * dell'ultimo fiscal_number già registrato in `ditron_receipts` — significa che la
     * cassa ha emesso qualcosa di nuovo dopo il nostro tentativo. Altrimenti restituisce
     * null (non promuoviamo a sent).
     *
     * @return array{fiscal_number: string, fiscal_date: string, z_number: ?int, matricola: string}|null
     */
    private function tryRecoverFromCash(DitronReceipt $receipt): ?array
    {
        $lastKnown = (int) DitronReceipt::query()
            ->where('id', '!=', $receipt->id)
            ->whereNotNull('fiscal_number')
            ->max('fiscal_number');

        $props = $this->readProperties([1, 10, 12]);
        if (!$props['ok']) {
            return null;
        }

        $values     = (array) ($props['values'] ?? []);
        $matricola  = $values[1] ?? null;
        $fiscalStr  = $values[10] ?? null;
        $lastZorDt  = $values[12] ?? null;

        if (!$matricola || !$fiscalStr || !ctype_digit((string) $fiscalStr)) {
            return null;
        }

        $fiscalNum = (int) $fiscalStr;
        if ($fiscalNum <= $lastKnown) {
            return null;
        }

        $zNumber = null;
        $fiscalDate = null;
        if ($lastZorDt !== null && ctype_digit((string) $lastZorDt)) {
            $zNumber = (int) $lastZorDt;
            $fiscalDate = now()->toDateString();
        } elseif (!empty($lastZorDt)) {
            try {
                $fiscalDate = Carbon::parse($lastZorDt)->toDateString();
            } catch (Throwable) {
                $fiscalDate = now()->toDateString();
            }
        } else {
            $fiscalDate = now()->toDateString();
        }

        $this->log('warning', 'Ditron recovery via GETP: scontrino emesso nonostante warning del WinEcrCom', $receipt->getLogContext() + [
            'recovered_fiscal_number'  => $fiscalNum,
            'recovered_z_number'       => $zNumber,
            'recovered_matricola'      => $matricola,
            'last_known_fiscal_number' => $lastKnown,
        ]);

        return [
            'fiscal_number' => (string) $fiscalNum,
            'fiscal_date'   => $fiscalDate,
            'z_number'      => $zNumber,
            'matricola'     => (string) $matricola,
        ];
    }

    /**
     * Sceglie la fiscal_date da persistere: preferisce quella del recovery se presente,
     * altrimenti quella del body dell'agent.
     */
    private function pickFiscalDate(?array $recovered, ?array $body): ?string
    {
        if (isset($recovered['fiscal_date'])) {
            return $recovered['fiscal_date'];
        }
        if (isset($body['fiscal_date'])) {
            try {
                return Carbon::parse($body['fiscal_date'])->toDateString();
            } catch (Throwable) {
                return null;
            }
        }
        return null;
    }

    private function isEnabled(): bool
    {
        return (bool) Setting::get('corrispettivo_enabled', true);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(self::LOG_CHANNEL)->log($level, '[Ditron] ' . $message, $context);
    }
}
