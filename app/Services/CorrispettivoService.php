<?php

namespace App\Services;

use App\Jobs\SendCorrispettivoJob;
use App\Models\PrecontoSplit;
use App\Models\Setting;
use App\Models\TableOrder;
use App\Models\TableOrderCorrispettivo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrazione dell'emissione/annullo dei corrispettivi elettronici.
 *
 * Incapsula la costruzione del payload, la chiamata al MysondFatturaService
 * (con timeout configurabile e mock in sviluppo), la persistenza del record
 * TableOrderCorrispettivo e la policy di retry asincrono.
 */
class CorrispettivoService
{
    /**
     * Whitelist esplicita: il corrispettivo va emesso SOLO per un incasso effettivo
     * in contanti o pagamento elettronico (POS). Qualsiasi altro metodo (chiusura_conto,
     * fattura_*, bonifico, assegno, misto, ecc.) NON deve emettere il corrispettivo.
     */
    private const PAYMENT_METHODS_ALLOWED = [
        'contanti',
        'pos',
    ];

    /** Log channel dedicato per tracciare l'intero flusso. */
    private const LOG_CHANNEL = 'corrispettivi';

    public function __construct(
        private MysondFatturaService $mysond,
    ) {}

    // ---------------------------------------------------------------------
    // API pubblica
    // ---------------------------------------------------------------------

    /**
     * Emissione corrispettivo per un tavolo (pagamento unico).
     * Crea il record, tenta l'invio sincrono con timeout e schedula
     * un job di retry in caso di fallimento.
     */
    public function emettiPerOrdine(TableOrder $order, string $paymentMethod, ?int $operatorId): ?TableOrderCorrispettivo
    {
        if ($this->isExcludedPaymentMethod($paymentMethod)) {
            $this->log('info', 'Emissione saltata: metodo non ammesso (solo contanti/pos)', [
                'table_order_id' => $order->id,
                'payment_method' => $paymentMethod,
            ]);
            return null;
        }
        if (!$this->isEnabled()) {
            $this->log('info', 'Emissione saltata: corrispettivi disabilitati da settings', [
                'table_order_id' => $order->id,
            ]);
            return null;
        }

        $corrispettivo = $this->createRecord(
            tipo: TableOrderCorrispettivo::TIPO_EMISSIONE,
            paymentMethod: $paymentMethod,
            operatorId: $operatorId,
            tableOrderId: $order->id,
            precontoSplitId: null,
            importi: $this->computeImportiForOrder($order),
        );

        $this->log('info', 'Inizio emissione corrispettivo per ordine', $corrispettivo->getLogContext() + [
            'importo' => (float) $corrispettivo->importo_totale,
        ]);

        $this->attemptSync($corrispettivo);
        return $corrispettivo;
    }

    /**
     * Emissione corrispettivo per un singolo preconto (split).
     * Chiamata per ogni split pagato.
     */
    public function emettiPerSplit(PrecontoSplit $split, string $paymentMethod, ?int $operatorId): ?TableOrderCorrispettivo
    {
        if ($this->isExcludedPaymentMethod($paymentMethod)) {
            $this->log('info', 'Emissione split saltata: metodo non ammesso (solo contanti/pos)', [
                'preconto_split_id' => $split->id,
                'payment_method'    => $paymentMethod,
            ]);
            return null;
        }
        if (!$this->isEnabled()) {
            $this->log('info', 'Emissione split saltata: corrispettivi disabilitati', [
                'preconto_split_id' => $split->id,
            ]);
            return null;
        }

        $corrispettivo = $this->createRecord(
            tipo: TableOrderCorrispettivo::TIPO_EMISSIONE,
            paymentMethod: $paymentMethod,
            operatorId: $operatorId,
            tableOrderId: $split->table_order_id,
            precontoSplitId: $split->id,
            importi: $this->computeImportiForSplit($split),
        );

        $this->log('info', 'Inizio emissione corrispettivo per split', $corrispettivo->getLogContext() + [
            'importo' => (float) $corrispettivo->importo_totale,
        ]);

        $this->attemptSync($corrispettivo);
        return $corrispettivo;
    }

    /**
     * Esegue un nuovo tentativo di invio per un corrispettivo pending/failed.
     * Usato sia dal job di retry sia dal pulsante "Riprova" nel backoffice.
     */
    public function riprova(TableOrderCorrispettivo $corrispettivo): TableOrderCorrispettivo
    {
        $this->log('info', 'Retry tentato', $corrispettivo->getLogContext());

        if ($corrispettivo->isSent()) {
            return $corrispettivo;
        }

        $this->doSend($corrispettivo);
        return $corrispettivo->fresh();
    }

    /**
     * Annullo di un corrispettivo emesso: crea un nuovo record tipo='annullo'
     * che punta all'emissione originale e chiama annullaCorrispettivo.
     */
    public function annulla(TableOrderCorrispettivo $emissione, ?int $operatorId): TableOrderCorrispettivo
    {
        if (!$emissione->canCancel()) {
            throw new \RuntimeException('Il corrispettivo non è in uno stato annullabile.');
        }

        $annullo = TableOrderCorrispettivo::create([
            'table_order_id'           => $emissione->table_order_id,
            'preconto_split_id'        => $emissione->preconto_split_id,
            'tipo'                     => TableOrderCorrispettivo::TIPO_ANNULLO,
            'annulla_corrispettivo_id' => $emissione->id,
            'payment_method'           => $emissione->payment_method,
            'importo_totale'           => $emissione->importo_totale,
            'imponibile'               => $emissione->imponibile,
            'iva'                      => $emissione->iva,
            'aliquota_iva'             => $emissione->aliquota_iva,
            'status'                   => TableOrderCorrispettivo::STATUS_PENDING,
            'max_attempts'             => (int) Setting::get('corrispettivo_max_attempts', 3),
            'operator_id'              => $operatorId,
        ]);

        $this->log('info', 'Inizio annullo corrispettivo', $annullo->getLogContext() + [
            'progressivo_sdi' => $emissione->progressivo_sdi,
        ]);

        $this->doAnnullo($annullo, $emissione->progressivo_sdi);
        return $annullo->fresh();
    }

    // ---------------------------------------------------------------------
    // Costruzione record + importi
    // ---------------------------------------------------------------------

    private function createRecord(
        string $tipo,
        string $paymentMethod,
        ?int $operatorId,
        ?int $tableOrderId,
        ?int $precontoSplitId,
        array $importi,
    ): TableOrderCorrispettivo {
        return TableOrderCorrispettivo::create([
            'table_order_id'    => $tableOrderId,
            'preconto_split_id' => $precontoSplitId,
            'tipo'              => $tipo,
            'payment_method'    => $paymentMethod,
            'importo_totale'    => $importi['totale'],
            'imponibile'        => $importi['imponibile'],
            'iva'               => $importi['iva'],
            'aliquota_iva'      => $importi['aliquota'],
            'status'            => TableOrderCorrispettivo::STATUS_PENDING,
            'max_attempts'      => (int) Setting::get('corrispettivo_max_attempts', 3),
            'operator_id'       => $operatorId,
        ]);
    }

    /**
     * Calcola imponibile/IVA/totale per un TableOrder.
     * Per ora IVA unica da settings (22% default).
     * TODO: quando i piatti avranno un'aliquota specifica, iterare sulle righe.
     */
    private function computeImportiForOrder(TableOrder $order): array
    {
        $totale = $order->hasDiscount()
            ? $order->getDiscountedTotal()
            : (float) $order->total_amount;

        return $this->splitImponibile($totale);
    }

    private function computeImportiForSplit(PrecontoSplit $split): array
    {
        return $this->splitImponibile((float) $split->total);
    }

    /**
     * Dato un totale lordo e un'aliquota X%, calcola imponibile e IVA.
     * imponibile = totale / (1 + X/100), iva = totale - imponibile.
     */
    private function splitImponibile(float $totale): array
    {
        $aliquota = (float) Setting::get('corrispettivo_aliquota_iva_default', 22.00);
        $imponibile = round($totale / (1 + $aliquota / 100), 2);
        $iva = round($totale - $imponibile, 2);

        return [
            'totale'     => round($totale, 2),
            'imponibile' => $imponibile,
            'iva'        => $iva,
            'aliquota'   => $aliquota,
        ];
    }

    // ---------------------------------------------------------------------
    // Flusso di invio
    // ---------------------------------------------------------------------

    /**
     * Primo tentativo sincrono. Se fallisce, schedula il job di retry.
     */
    private function attemptSync(TableOrderCorrispettivo $corrispettivo): void
    {
        $this->doSend($corrispettivo);

        $corrispettivo->refresh();
        if ($corrispettivo->isFailed() && $corrispettivo->attempts < $corrispettivo->max_attempts) {
            $delay = $this->retryDelayForAttempt($corrispettivo->attempts);
            $this->log('info', 'Dispatch job di retry corrispettivo', $corrispettivo->getLogContext() + [
                'delay_seconds' => $delay,
            ]);
            SendCorrispettivoJob::dispatch($corrispettivo->id)
                ->onQueue('corrispettivi')
                ->delay(now()->addSeconds($delay));
        }
    }

    /**
     * Esegue la chiamata SOAP (o mock), aggiorna il record con esito e tempo.
     */
    private function doSend(TableOrderCorrispettivo $corrispettivo): void
    {
        DB::transaction(function () use ($corrispettivo) {
            $locked = TableOrderCorrispettivo::lockForUpdate()->find($corrispettivo->id);
            if (!$locked || $locked->isSent()) {
                return;
            }

            $locked->update([
                'status'   => TableOrderCorrispettivo::STATUS_SENDING,
                'attempts' => $locked->attempts + 1,
            ]);

            $attempt = $locked->attempts;
            $payload = $this->buildPayload($locked);
            $timeout = (int) Setting::get('corrispettivo_timeout_seconds', 10);
            $startedAt = microtime(true);

            $this->log('info', 'Pre-chiamata SOAP inviaCorrispettivo', $locked->getLogContext() + [
                'attempt'         => $attempt,
                'timeout_seconds' => $timeout,
                'totale'          => (float) $locked->importo_totale,
                'n_righe'         => count($payload['corrispettivoRigaItemList'] ?? []),
            ]);

            try {
                $response = $this->invoca('inviaCorrispettivo', fn() => $this->mysond->inviaCorrispettivo($payload), $timeout);

                $parsed = $this->parseMysondResponse($response);
                $progressivo = $parsed['progressivo'] ?? null;
                $identificativo = $parsed['idtrx'] ?? null;
                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

                if (!$progressivo) {
                    throw new \RuntimeException($parsed['error'] ?? 'Risposta SOAP senza progressivo');
                }

                $locked->update([
                    'status'             => TableOrderCorrispettivo::STATUS_SENT,
                    'progressivo_sdi'    => $progressivo,
                    'identificativo_sdi' => $identificativo,
                    'soap_request'       => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'soap_response'      => $this->encodeResponse($response),
                    'sent_at'            => now(),
                    'last_error'         => null,
                ]);

                $this->log('info', 'Corrispettivo inviato con successo', $locked->getLogContext() + [
                    'attempt'         => $attempt,
                    'progressivo_sdi' => $progressivo,
                    'duration_ms'     => $durationMs,
                    'mock'            => $this->isMockEnabled(),
                ]);

                $this->printReceiptSafe($locked);
            } catch (Throwable $e) {
                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                $isFinal = $locked->attempts >= $locked->max_attempts;

                $locked->update([
                    'status'        => TableOrderCorrispettivo::STATUS_FAILED,
                    'last_error'    => $e->getMessage(),
                    'soap_request'  => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'soap_response' => null,
                ]);

                $level = $isFinal ? 'error' : 'warning';
                $this->log($level, 'Errore invio corrispettivo', $locked->getLogContext() + [
                    'attempt'     => $attempt,
                    'duration_ms' => $durationMs,
                    'error'       => $e->getMessage(),
                    'final'       => $isFinal,
                ]);
            }
        });
    }

    /**
     * Invio dell'annullo. Non schedula retry automatico: l'annullo è manuale
     * e può essere ritentato dal backoffice.
     */
    private function doAnnullo(TableOrderCorrispettivo $annullo, string $progressivoSdi): void
    {
        DB::transaction(function () use ($annullo, $progressivoSdi) {
            $locked = TableOrderCorrispettivo::lockForUpdate()->find($annullo->id);
            if (!$locked) {
                return;
            }

            $locked->update([
                'status'   => TableOrderCorrispettivo::STATUS_SENDING,
                'attempts' => $locked->attempts + 1,
            ]);

            $attempt = $locked->attempts;
            $timeout = (int) Setting::get('corrispettivo_timeout_seconds', 10);
            $startedAt = microtime(true);

            $this->log('info', 'Pre-chiamata SOAP annullaCorrispettivo', $locked->getLogContext() + [
                'progressivo_sdi' => $progressivoSdi,
                'timeout_seconds' => $timeout,
            ]);

            try {
                $response = $this->invoca(
                    'annullaCorrispettivo',
                    fn() => $this->mysond->annullaCorrispettivo($progressivoSdi),
                    $timeout,
                );

                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

                $locked->update([
                    'status'          => TableOrderCorrispettivo::STATUS_SENT,
                    'progressivo_sdi' => $progressivoSdi,
                    'soap_request'    => json_encode(['progressivoSdi' => $progressivoSdi]),
                    'soap_response'   => $this->encodeResponse($response),
                    'sent_at'         => now(),
                    'last_error'      => null,
                ]);

                $emissione = $locked->emissioneAnnullata;
                if ($emissione) {
                    $emissione->update(['status' => TableOrderCorrispettivo::STATUS_CANCELLED]);
                }

                $this->log('info', 'Corrispettivo annullato con successo', $locked->getLogContext() + [
                    'attempt'     => $attempt,
                    'duration_ms' => $durationMs,
                    'mock'        => $this->isMockEnabled(),
                ]);
            } catch (Throwable $e) {
                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                $locked->update([
                    'status'     => TableOrderCorrispettivo::STATUS_FAILED,
                    'last_error' => $e->getMessage(),
                ]);

                $this->log('error', 'Errore annullo corrispettivo', $locked->getLogContext() + [
                    'attempt'     => $attempt,
                    'duration_ms' => $durationMs,
                    'error'       => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Wrap della chiamata SOAP con timeout e mock.
     */
    private function invoca(string $methodName, callable $call, int $timeoutSeconds): mixed
    {
        if ($this->isMockEnabled()) {
            usleep(50_000);
            return $this->buildMockResponse($methodName);
        }

        $this->mysond->setWsdl('CorrispettivoElettronicoService');

        $previousTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', (string) $timeoutSeconds);
        try {
            return $call();
        } finally {
            ini_set('default_socket_timeout', (string) $previousTimeout);
        }
    }

    // ---------------------------------------------------------------------
    // Payload
    // ---------------------------------------------------------------------

    /**
     * Costruisce la struttura 'corrispettivoTestataItem' per inviaCorrispettivo.
     *
     * Mappiamo dati reali quando disponibili; i default sono marcati TODO
     * per essere adeguati alla risposta/spec reale di Mysond.
     */
    private function buildPayload(TableOrderCorrispettivo $corrispettivo): array
    {
        $righe = [];
        if ($corrispettivo->preconto_split_id) {
            $righe = $this->buildRigheFromSplit($corrispettivo);
        } elseif ($corrispettivo->table_order_id) {
            $righe = $this->buildRigheFromOrder($corrispettivo);
        }

        return [
            'dataDoc'              => now()->format('Y-m-d\TH:i:s'),
            'importoTotaleIva'     => (float) $corrispettivo->iva,
            'scontoTotale'         => 0,
            'scontoTotaleLordo'    => 0,
            'scontoAbbuono'        => 0,
            'totaleImponibile'     => (float) $corrispettivo->imponibile,
            'ammontareComplessivo' => (float) $corrispettivo->importo_totale,
            // TODO: verificare codici pagamento accettati da ADE.
            // Default: PE = pagamento elettronico (POS), PO = contanti.
            'pagamento'            => $this->mapPagamento($corrispettivo->payment_method),
            'corrispettivoRigaItemList' => $righe,
        ];
    }

    private function buildRigheFromOrder(TableOrderCorrispettivo $corrispettivo): array
    {
        $order = TableOrder::with('items.dish')->find($corrispettivo->table_order_id);
        if (!$order) {
            return $this->buildRigaDefault($corrispettivo);
        }

        $righe = [];
        foreach ($order->items as $item) {
            if ($item->isSegueItem() || $item->subtotal <= 0) {
                continue;
            }
            $righe[] = $this->buildRigaFromItem(
                descrizione: $item->dish->label ?? $item->dish->name ?? 'Articolo',
                quantita:    (int) $item->quantity,
                totaleLordo: (float) $item->subtotal,
                aliquota:    (float) $corrispettivo->aliquota_iva,
            );
        }
        if ($order->hasCoverCharge()) {
            $righe[] = $this->buildRigaFromItem(
                descrizione: 'Coperto',
                quantita:    (int) $order->covers,
                totaleLordo: (float) $order->getCoverChargeAmount(),
                aliquota:    (float) $corrispettivo->aliquota_iva,
            );
        }

        return $righe ?: $this->buildRigaDefault($corrispettivo);
    }

    private function buildRigheFromSplit(TableOrderCorrispettivo $corrispettivo): array
    {
        $split = PrecontoSplit::with('order')->find($corrispettivo->preconto_split_id);
        if (!$split || empty($split->items)) {
            return $this->buildRigaDefault($corrispettivo);
        }

        $righe = [];
        foreach ($split->items as $item) {
            $qty   = (int) ($item['quantity'] ?? $item['qty'] ?? 1);
            $price = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
            $desc  = (string) ($item['dish_name'] ?? $item['name'] ?? 'Articolo');
            $tot   = $price * $qty;
            if ($tot <= 0) {
                continue;
            }
            $righe[] = $this->buildRigaFromItem($desc, $qty, $tot, (float) $corrispettivo->aliquota_iva);
        }

        // Coperti associati a questo split (se presenti)
        if ($split->covers > 0 && $split->order) {
            $coverPerPerson = (float) $split->order->getCoverChargePerPerson();
            $coverTotal = round($coverPerPerson * (int) $split->covers, 2);
            if ($coverTotal > 0) {
                $righe[] = $this->buildRigaFromItem(
                    'Coperto',
                    (int) $split->covers,
                    $coverTotal,
                    (float) $corrispettivo->aliquota_iva,
                );
            }
        }

        return $righe ?: $this->buildRigaDefault($corrispettivo);
    }

    private function buildRigaFromItem(string $descrizione, int $quantita, float $totaleLordo, float $aliquota): array
    {
        $quantita = max(1, $quantita);
        $prezzoUnitario = round($totaleLordo / $quantita, 2);
        $imponibile = round($totaleLordo / (1 + $aliquota / 100), 2);
        $iva = round($totaleLordo - $imponibile, 2);

        return [
            'quantita'         => $quantita,
            'descrizione'      => mb_substr($descrizione, 0, 60),
            'prezzoLordo'      => $totaleLordo,
            'prezzoUnitario'   => $prezzoUnitario,
            'scontoUnitario'   => 0,
            'scontoLordo'      => 0,
            'aliquotaIva'      => $aliquota,
            'importoIva'       => $iva,
            'imponibile'       => $imponibile,
            'imponibileNetto'  => $imponibile,
            'totale'           => $totaleLordo,
        ];
    }

    private function buildRigaDefault(TableOrderCorrispettivo $corrispettivo): array
    {
        return [
            $this->buildRigaFromItem(
                'Corrispettivo',
                1,
                (float) $corrispettivo->importo_totale,
                (float) $corrispettivo->aliquota_iva,
            ),
        ];
    }

    private function mapPagamento(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'pos'      => 'PE',
            'contanti' => 'PO',
            default    => 'PO',
        };
    }

    // ---------------------------------------------------------------------
    // Mock + parsing risposta
    // ---------------------------------------------------------------------

    /**
     * Costruisce una risposta mock che rispetta la struttura reale Mysond:
     * { esito: 0, messaggio: json-string { esito: true, idtrx, progressivo, errori: [] } }
     */
    private function buildMockResponse(string $methodName): object
    {
        $rand = random_int(1000000, 9999999);
        $inner = [
            'esito'       => true,
            'idtrx'       => 'MOCK' . $rand,
            'progressivo' => 'MOCK' . now()->format('Ymd') . $rand,
            'errori'      => [],
        ];

        return (object) [
            'esito'     => 0,
            'messaggio' => json_encode($inner, JSON_UNESCAPED_UNICODE),
            'mock'      => true,
            'metodo'    => $methodName,
        ];
    }

    /**
     * Decodifica la risposta Mysond di inviaCorrispettivo secondo il modello reale:
     *   - Campo `esito` (int): 0 = OK, != 0 = errore a livello SOAP/Mysond.
     *   - Campo `messaggio` (string): in caso di esito 0 contiene un JSON
     *     { "esito": bool, "idtrx": string, "progressivo": string, "errori": [] }.
     *
     * Ritorna un array ['progressivo' => ?string, 'idtrx' => ?string, 'error' => ?string].
     */
    private function parseMysondResponse(mixed $response): array
    {
        $esito     = $this->readField($response, 'esito');
        $messaggio = $this->readField($response, 'messaggio');

        // Errore a livello SOAP/Mysond: il messaggio è il testo d'errore.
        if ($esito !== null && (int) $esito !== 0) {
            return [
                'progressivo' => null,
                'idtrx'       => null,
                'error'       => is_string($messaggio) && $messaggio !== ''
                    ? "Mysond esito={$esito}: {$messaggio}"
                    : "Mysond esito={$esito}",
            ];
        }

        if (!is_string($messaggio) || $messaggio === '') {
            return ['progressivo' => null, 'idtrx' => null, 'error' => 'Risposta Mysond senza messaggio'];
        }

        $inner = json_decode($messaggio, true);
        if (!is_array($inner)) {
            return ['progressivo' => null, 'idtrx' => null, 'error' => 'messaggio non è un JSON valido'];
        }

        // Il JSON interno può indicare errori applicativi di ADE.
        $innerOk = !empty($inner['esito']);
        $errori  = $inner['errori'] ?? [];
        if (!$innerOk || (is_array($errori) && count($errori) > 0)) {
            $errText = is_array($errori) ? implode('; ', array_map(fn($e) => is_string($e) ? $e : json_encode($e), $errori)) : (string) $errori;
            return [
                'progressivo' => null,
                'idtrx'       => null,
                'error'       => 'ADE ha rifiutato l\'emissione: ' . ($errText ?: 'esito=false'),
            ];
        }

        return [
            'progressivo' => !empty($inner['progressivo']) ? (string) $inner['progressivo'] : null,
            'idtrx'       => !empty($inner['idtrx'])       ? (string) $inner['idtrx']       : null,
            'error'       => null,
        ];
    }

    /**
     * Legge un campo sia da oggetto (SOAP) sia da array, gestendo anche response->return.
     */
    private function readField(mixed $response, string $key): mixed
    {
        if (is_object($response)) {
            if (isset($response->{$key})) {
                return $response->{$key};
            }
            if (isset($response->return) && is_object($response->return) && isset($response->return->{$key})) {
                return $response->return->{$key};
            }
        }
        if (is_array($response)) {
            if (array_key_exists($key, $response)) {
                return $response[$key];
            }
            if (isset($response['return']) && is_array($response['return']) && array_key_exists($key, $response['return'])) {
                return $response['return'][$key];
            }
        }
        return null;
    }

    private function encodeResponse(mixed $response): string
    {
        try {
            return json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: (string) print_r($response, true);
        } catch (Throwable) {
            return (string) print_r($response, true);
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function isEnabled(): bool
    {
        return (bool) Setting::get('corrispettivo_enabled', true);
    }

    private function isMockEnabled(): bool
    {
        return (bool) Setting::get('corrispettivo_mock', true);
    }

    private function isExcludedPaymentMethod(string $method): bool
    {
        return !in_array($method, self::PAYMENT_METHODS_ALLOWED, true);
    }

    /**
     * Backoff progressivo per i retry: 30s, 120s, 300s.
     */
    private function retryDelayForAttempt(int $currentAttempts): int
    {
        return match ($currentAttempts) {
            1       => 30,
            2       => 120,
            default => 300,
        };
    }

    private function printReceiptSafe(TableOrderCorrispettivo $corrispettivo): void
    {
        try {
            app(PrinterService::class)->printCorrispettivoReceipt($corrispettivo);
        } catch (Throwable $e) {
            $this->log('error', 'Stampa scontrino fallita (corrispettivo comunque inviato)', $corrispettivo->getLogContext() + [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(self::LOG_CHANNEL)->{$level}($message, $context);
    }
}
