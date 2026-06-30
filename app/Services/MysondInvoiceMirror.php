<?php

namespace App\Services;

use App\Exceptions\PendingSdiRejectionsException;
use App\Models\MirroredInvoice;
use App\Models\Setting;
use App\Models\TableOrderInvoice;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pull on-demand di tutte le fatture visibili sull'Azienda MySond verso la
 * tabella locale `mirrored_invoices`. MySond è autorevole; questa è una
 * proiezione consultabile senza altre chiamate SOAP per ogni refresh pagina.
 *
 * Trigger previsti:
 *   - all'apertura di /backoffice/accounting (lista fatture)
 *   - prima di ogni emissione (vedi callsite TableOrderController.payTableInvoice
 *     e QuickInvoiceWizard.submit) — chiamato via runOrThrow()
 *
 * Responsabilità in un singolo run:
 *   1. sync() — upsert mirrored_invoices dal feed MySond, riconciliando lo
 *      stato sulle TableOrderInvoice locali matching.
 *   2. allinea il contatore locale `invoice_counter` al massimo Numero su
 *      MySond per evitare collisioni con fatture emesse dall'altro progetto.
 *   3. notifica Telegram quando trova scartate nuove.
 *
 * runOrThrow() aggiunge un quarto step opzionale: lancia
 * PendingSdiRejectionsException se ci sono scartate non riconosciute. Usata
 * dai callsite di emissione, non dalla view-only.
 *
 * Tutte le interazioni SOAP sono fail-soft: se MySond è irraggiungibile
 * lasciamo lo stato locale invariato e l'app prosegue.
 */
class MysondInvoiceMirror
{
    public function __construct(private readonly MysondFatturaService $mysond)
    {
    }

    public function sync(?int $year = null): void
    {
        if (! $this->mysond->isConfigured()) {
            return;
        }

        $year ??= (int) now()->format('Y');

        try {
            $items = $this->mysond->getFeInviateLink($year);
        } catch (Throwable $e) {
            Log::warning('MysondInvoiceMirror: SOAP probe failed', [
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        if (empty($items)) {
            return;
        }

        // Primo run: importiamo lo storico come auto-ack-ato così non blocca.
        $bootstrap = MirroredInvoice::count() === 0;

        $newRejections = [];
        $maxNumero = null;

        foreach ($items as $item) {
            $fileName = (string) ($item->docName ?? $item->fileName ?? '');
            if ($fileName === '') {
                continue;
            }
            $fileName = preg_replace('/\.xml$/i', '', $fileName);

            $stato = isset($item->stato) && is_numeric($item->stato) ? (int) $item->stato : null;
            $code  = isset($item->code) ? (string) $item->code : null;

            // Match con eventuale fattura locale per popolare local_invoice_id
            // e mantenere allineato sdi_status sulle TableOrderInvoice.
            $localInvoice = $this->matchLocalInvoice($fileName);

            $payload = [
                'file_name'       => $fileName,
                'mysond_code'     => $code,
                'mysond_date'     => $this->itemDate($item),
                'mysond_total'    => $this->itemTotal($item),
                'customer_name'   => $this->itemString($item, ['intestazione', 'denominazione', 'destinatario']),
                'customer_vat'    => $this->itemString($item, ['piva', 'partitaIva', 'pivaCessionario']),
                'customer_cf'     => $this->itemString($item, ['cf', 'codiceFiscale', 'cfCessionario']),
                'stato'           => $stato,
                'stato_label'     => $stato !== null ? TableOrderInvoice::sdiStatusLabel($stato) : null,
                'local_invoice_id' => $localInvoice?->id,
                'last_synced_at'  => now(),
            ];

            $existing = MirroredInvoice::where('file_name', $fileName)->first();

            if ($existing) {
                $wasPendingRejection = $existing->isPendingAck();
                $existing->fill($payload)->save();
            } else {
                $payload['first_synced_at'] = now();
                if ($bootstrap) {
                    $payload['acknowledged_at']   = now();
                    $payload['acknowledged_by']   = 'system';
                    $payload['acknowledged_note'] = 'Pre-esistente all\'attivazione del controllo SDI';
                }
                $existing = MirroredInvoice::create($payload);

                if (! $bootstrap && $existing->isPendingAck()) {
                    $newRejections[] = $existing;
                }
                $wasPendingRejection = false;
            }

            // Riconcilia lo stato SDI sulla TableOrderInvoice locale, se esiste:
            // mantiene allineato anche il vecchio modello senza dover chiamare
            // mysond:refresh-sdi separatamente.
            if ($localInvoice && $stato !== null && (int) $localInvoice->sdi_status !== $stato) {
                $localInvoice->update([
                    'sdi_status'       => $stato,
                    'sdi_status_label' => TableOrderInvoice::sdiStatusLabel($stato),
                    'sdi_checked_at'   => now(),
                ]);
            }

            // Traccia max numero per il sync contatore.
            if ($code !== null) {
                $n = $this->numeroToInt($code);
                if ($n !== null && ($maxNumero === null || $n > $maxNumero)) {
                    $maxNumero = $n;
                }
            }
        }

        $this->syncCounter($maxNumero);

        if (! empty($newRejections)) {
            $this->notifyTelegram($newRejections);
        }
    }

    /**
     * Esegui sync e lancia PendingSdiRejectionsException se ci sono scartate
     * non riconosciute. Da usare nei callsite di emissione.
     */
    public function runOrThrow(): void
    {
        $this->sync();

        if (! config('services.mysond.block_on_unack_rejections', true)) {
            return;
        }

        $pending = MirroredInvoice::pendingAck()->orderBy('first_synced_at')->get();
        if ($pending->isNotEmpty()) {
            throw new PendingSdiRejectionsException($pending);
        }
    }

    private function syncCounter(?int $maxNumero): void
    {
        if ($maxNumero === null) {
            return;
        }
        if (! config('services.mysond.sync_counter_on_issue', true)) {
            return;
        }

        $local = (int) Setting::get('invoice_counter', 0);
        if ($maxNumero > $local) {
            Log::info('MysondInvoiceMirror: bumping invoice_counter to match MySond', [
                'from' => $local,
                'to'   => $maxNumero,
            ]);
            Setting::set('invoice_counter', $maxNumero, 'integer');
        }
    }

    private function matchLocalInvoice(string $fileName): ?TableOrderInvoice
    {
        // Convenzione: MySond accumula i file come "IT{vat}_{invoice_name}".
        // Estraiamo invoice_name e cerchiamo nella locale.
        if (preg_match('/_([A-Z0-9]+)$/i', $fileName, $m)) {
            return TableOrderInvoice::where('invoice_name', $m[1])->first();
        }
        return null;
    }

    private function itemDate($item): ?string
    {
        $date = $item->date ?? $item->dateLong ?? null;
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }
        if (is_int($date) || (is_string($date) && ctype_digit($date))) {
            $ts = (int) $date;
            if ($ts > 10_000_000_000) {
                $ts = (int) ($ts / 1000);
            }
            return date('Y-m-d', $ts);
        }
        if (is_string($date) && preg_match('/(\d{4}-\d{2}-\d{2})/', $date, $m)) {
            return $m[1];
        }
        return null;
    }

    private function itemTotal($item): ?float
    {
        foreach (['importoTotale', 'totale', 'importo'] as $k) {
            if (isset($item->{$k}) && is_numeric($item->{$k})) {
                return (float) $item->{$k};
            }
        }
        return null;
    }

    private function itemString($item, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (isset($item->{$k}) && $item->{$k} !== '') {
                return (string) $item->{$k};
            }
        }
        return null;
    }

    private function numeroToInt(string $numero): ?int
    {
        if (preg_match('/-(\d+)\s*$/', $numero, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^\s*(\d+)/', $numero, $m)) {
            return (int) $m[1];
        }
        if (preg_match_all('/\d+/', $numero, $m)) {
            return max(array_map('intval', $m[0]));
        }
        return null;
    }

    /**
     * @param array<int, MirroredInvoice> $newRejections
     */
    private function notifyTelegram(array $newRejections): void
    {
        if (! config('logging.channels.telegram.handler_with.apiKey')) {
            return;
        }

        $lines = [
            '🚨 <b>Scartate SDI rilevate su MySond</b> (' . count($newRejections) . ')',
            '',
        ];
        foreach ($newRejections as $r) {
            $lines[] = sprintf(
                '• <code>%s</code> — n. %s — %s',
                htmlspecialchars($r->file_name, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($r->mysond_code ?? '?'), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string) ($r->stato_label ?? ''), ENT_QUOTES, 'UTF-8'),
            );
        }
        $lines[] = '';
        $lines[] = '⛔️ Nuove emissioni fattura bloccate finché non vengono riconosciute dal backoffice.';

        try {
            Log::channel('telegram')->warning(implode("\n", $lines));
        } catch (Throwable $e) {
            Log::warning('MysondInvoiceMirror: Telegram notify failed', ['err' => $e->getMessage()]);
        }
    }
}
