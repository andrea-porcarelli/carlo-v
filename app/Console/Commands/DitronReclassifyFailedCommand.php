<?php

namespace App\Console\Commands;

use App\Models\DitronReceipt;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Riclassifica gli scontrini Ditron marcati `failed` che in realtà erano stati emessi
 * correttamente dalla cassa. Fino alla fix in ScontriniWriter.cs, l'agent trattava
 * ogni contenuto non-whitespace di scontrinoNN.err come errore, includendo warning
 * benigni. Qui rileggiamo `raw_err` e riapplichiamo la stessa logica keyword del
 * writer nuovo, poi promuoviamo a `sent` chi si rivela un warning e non un errore.
 *
 * Default dry-run: mostra soltanto quali record cambierebbero. Con --apply committa.
 */
class DitronReclassifyFailedCommand extends Command
{
    protected $signature = 'ditron:reclassify-failed
                            {--apply : Committa le modifiche (senza flag è dry-run).}
                            {--from= : Data minima (YYYY-MM-DD) — default: 60 giorni fa.}
                            {--to= : Data massima (YYYY-MM-DD) — default: oggi.}
                            {--limit=500 : Numero massimo di record da esaminare.}';

    protected $description = 'Rilegge i failed Ditron e li ripromuove a sent se raw_err è un warning benigno.';

    /**
     * Regex — case-insensitive, multiline — che marcano il .err come errore reale.
     * Devono restare allineate a quelle dell'agent (DitronAgentOptions.ErrErrorKeywords).
     */
    private const ERROR_KEYWORDS = [
        '/\berrore\b/i',
        '/\berror\b/i',
        '/\babort(?:ed)?\b/i',
        '/\btimeout\b/i',
        '/\bfault\b/i',
        '/\bfail(?:ed|ure)?\b/i',
        '/\bimpossibile\b/i',
        '/\bnon\s+ammess/i',
        '/\billegale\b/i',
        '/^\s*\d+\s+\d+\s+\S/m',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $from = Carbon::parse($this->option('from') ?: Carbon::today()->subDays(60)->toDateString())->startOfDay();
        $to   = Carbon::parse($this->option('to') ?: Carbon::today()->toDateString())->endOfDay();
        $limit = (int) $this->option('limit');

        $mode = $apply ? 'APPLY' : 'DRY-RUN';
        $this->info("Riclassificazione failed Ditron [{$mode}] dal {$from->toDateString()} al {$to->toDateString()}, limite {$limit}");

        $rows = DitronReceipt::sales()
            ->where('status', DitronReceipt::STATUS_FAILED)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('raw_err')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Nessun record failed con raw_err nel periodo.');
            return self::SUCCESS;
        }

        $promoted = 0;
        $kept = 0;
        $emptyErr = 0;

        foreach ($rows as $r) {
            $raw = (string) $r->raw_err;
            if (trim($raw) === '') {
                // failed senza raw_err di solito è timeout — non tocchiamo, potrebbe essere davvero non emesso
                $emptyErr++;
                continue;
            }

            if ($this->isRealError($raw)) {
                $kept++;
                continue;
            }

            // classificato come warning benigno → lo scontrino era stato emesso
            $preview = str_replace(["\r", "\n"], ' ', trim($raw));
            $preview = mb_strimwidth($preview, 0, 80, '…');
            $this->line("  #{$r->id} €{$r->importo_totale} {$r->created_at} — err='{$preview}'");

            if ($apply) {
                $r->update([
                    'status'     => DitronReceipt::STATUS_SENT,
                    'sent_at'    => $r->sent_at ?? $r->created_at,
                    'last_error' => null,
                ]);
            }
            $promoted++;
        }

        $this->newLine();
        $this->info("Riepilogo: promossi={$promoted}, mantenuti-failed={$kept}, senza-raw_err={$emptyErr}");
        if (!$apply && $promoted > 0) {
            $this->warn("Nessuna modifica applicata (dry-run). Rilancia con --apply per committare.");
        }

        return self::SUCCESS;
    }

    private function isRealError(string $raw): bool
    {
        foreach (self::ERROR_KEYWORDS as $pattern) {
            if (preg_match($pattern, $raw)) {
                return true;
            }
        }
        return false;
    }
}
