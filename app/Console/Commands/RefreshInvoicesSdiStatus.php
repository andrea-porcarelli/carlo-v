<?php

namespace App\Console\Commands;

use App\Facades\Utils;
use App\Helpers\VatHelper;
use App\Models\InvoiceMysondLog;
use App\Models\TableOrderInvoice;
use App\Services\MysondFatturaService;
use App\Services\SdiStatusNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshInvoicesSdiStatus extends Command
{
    protected $signature = 'mysond:refresh-sdi';
    protected $description = 'Aggiorna lo stato SDI delle fatture inviate non ancora concluse (batch getUltimaNotificaList).';

    /**
     * Stati SDI da considerare "terminali" — non interrogati più.
     * 7=Consegnata, 9=Accettata PA, 10=Rifiutata PA, 6=Scartata,
     * 1=Rifiutata, 11=Decorrenza termini.
     */
    private const TERMINAL_STATUSES = [1, 6, 7, 9, 10, 11];

    /**
     * Massimo numero di fatture per chiamata SOAP (limite prudenziale).
     */
    private const BATCH_SIZE = 50;

    public function handle(MysondFatturaService $service): int
    {
        $vatDigits = VatHelper::sanitize(Utils::setting('company_vat_number'));
        if (strlen($vatDigits) !== 11) {
            $this->error('P.IVA azienda non valida nel setting company_vat_number.');
            return self::FAILURE;
        }

        $pending = TableOrderInvoice::query()
            ->where('status', 'sent')
            ->whereNotNull('invoice_name')
            ->where(function ($q) {
                $q->whereNull('sdi_status')
                  ->orWhereNotIn('sdi_status', self::TERMINAL_STATUSES);
            })
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Nessuna fattura da aggiornare.');
            return self::SUCCESS;
        }

        $this->info("Trovate {$pending->count()} fatture da controllare.");

        $updatedCount = 0;
        $changedCount = 0;

        foreach ($pending->chunk(self::BATCH_SIZE) as $batch) {
            // Mappa filename → invoice per il lookup veloce
            $byName = [];
            $fileNames = [];
            foreach ($batch as $inv) {
                $name = 'IT' . $vatDigits . '_' . $inv->invoice_name;
                $byName[$name] = $inv;
                $fileNames[] = $name;
            }

            $startedAt = microtime(true);
            try {
                $notifiche = $service->getUltimaNotificaList($fileNames);
            } catch (\Throwable $e) {
                Log::error('mysond:refresh-sdi batch error', ['error' => $e->getMessage()]);
                $this->error('Errore batch: ' . $e->getMessage());
                continue;
            }
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            foreach ($notifiche as $notifica) {
                $nomeFile = (string) ($notifica->nomeFile ?? $notifica->fileName ?? '');
                $nomeFile = preg_replace('/\.xml$/i', '', $nomeFile);

                if (!isset($byName[$nomeFile])) {
                    continue;
                }
                $invoice = $byName[$nomeFile];

                $code = null;
                foreach (['stato', 'esito', 'codice', 'codiceEsito'] as $k) {
                    if (isset($notifica->{$k}) && is_numeric($notifica->{$k})) {
                        $code = (int) $notifica->{$k};
                        break;
                    }
                }
                $descrizione = $notifica->descrizione ?? ($notifica->messaggio ?? null);
                $tipo        = $notifica->tipo ?? ($notifica->tipoNotifica ?? null);
                $label       = TableOrderInvoice::sdiStatusLabel($code);
                if (!$label && $tipo) {
                    $label = (string) $tipo;
                }

                $previousStatus = $invoice->sdi_status;
                $statusChanged  = $code !== null && $previousStatus !== $code;

                $invoice->update([
                    'sdi_status'       => $code,
                    'sdi_status_label' => $label,
                    'sdi_checked_at'   => now(),
                    'sdi_response'     => json_encode([
                        'tipo'        => $tipo,
                        'stato'       => $code,
                        'descrizione' => $descrizione,
                        'raw'         => json_decode(json_encode($notifica), true),
                        'at'          => now()->toIso8601String(),
                    ]),
                ]);

                InvoiceMysondLog::create([
                    'table_order_invoice_id' => $invoice->id,
                    'operation'              => 'getUltimaNotificaList',
                    'outcome'                => InvoiceMysondLog::OUTCOME_SUCCESS,
                    'esito'                  => $code,
                    'codice'                 => $tipo ? (string) $tipo : null,
                    'descrizione'            => $descrizione ?: $label,
                    'duration_ms'            => $durationMs,
                ]);

                $updatedCount++;
                if ($statusChanged) {
                    $changedCount++;
                    $invoice->loadMissing('customer');
                    SdiStatusNotifier::notifyStatusChange($invoice, $previousStatus, $code, $label, $descrizione);
                }
            }
        }

        $this->info("Aggiornate {$updatedCount} fatture, {$changedCount} con cambio di stato.");
        return self::SUCCESS;
    }
}
