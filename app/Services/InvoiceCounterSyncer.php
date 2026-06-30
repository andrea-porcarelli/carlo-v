<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Allinea il contatore locale `invoice_counter` con il massimo progressivo
 * già emesso su MySond per l'anno corrente, prima che venga incrementato per
 * una nuova fattura. Evita collisioni quando il contatore locale è andato
 * fuori sync (es. importazione, ripristino DB, fatture emesse direttamente
 * dal portale MySond).
 *
 * Fail-soft per design: qualsiasi errore (credenziali mancanti, MySond
 * irraggiungibile, lista vuota) viene loggato come warning e il contatore
 * locale resta com'è. L'emissione non deve mai essere bloccata da MySond.
 */
class InvoiceCounterSyncer
{
    public function __construct(private readonly MysondFatturaService $mysond)
    {
    }

    public function syncFromMysond(): void
    {
        if (!config('services.mysond.sync_counter_on_issue', true)) {
            return;
        }
        if (!$this->mysond->isConfigured()) {
            return;
        }

        $year = (int) now()->format('Y');

        try {
            $max = $this->mysond->maxIssuedNumberForYear($year);
        } catch (\Throwable $e) {
            Log::warning('InvoiceCounterSyncer: MySond probe failed, keeping local counter', [
                'year'  => $year,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        if ($max === null) {
            return;
        }

        $local = (int) Setting::get('invoice_counter', 0);
        if ($max > $local) {
            Log::info('InvoiceCounterSyncer: bumping invoice_counter to match MySond', [
                'year'      => $year,
                'from'      => $local,
                'to'        => $max,
            ]);
            Setting::set('invoice_counter', $max, 'integer');
        }
    }
}
