<?php

namespace App\Console\Commands;

use App\Models\DitronDailyClosure;
use App\Models\Setting;
use App\Services\DitronCloseDayService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DitronCloseDayCommand extends Command
{
    protected $signature = 'ditron:close-day
                            {--date= : Data della chiusura (YYYY-MM-DD). Default: oggi.}
                            {--source=auto : "auto" o "manual".}
                            {--operator= : ID operatore che ha lanciato la chiusura.}';

    protected $description = 'Esegue la chiusura giornaliera fiscale (Z) sulla cassa Ditron RT via DitronAgent.';

    public function handle(DitronCloseDayService $service): int
    {
        $provider = (string) Setting::get('corrispettivo_provider', 'mysond');
        if ($provider !== 'ditron') {
            $this->warn("Provider corrispettivi = '{$provider}'. Chiusura Ditron saltata.");
            return self::SUCCESS;
        }

        $dateOption = $this->option('date');
        $date = $dateOption ? Carbon::parse($dateOption) : Carbon::today();

        $source = $this->option('source') === DitronDailyClosure::SOURCE_MANUAL
            ? DitronDailyClosure::SOURCE_MANUAL
            : DitronDailyClosure::SOURCE_AUTO;

        $operatorId = $this->option('operator') !== null ? (int) $this->option('operator') : null;

        $this->info("Chiusura Ditron per {$date->toDateString()} (source={$source})…");

        $closure = $service->close($date, $source, $operatorId);

        if ($closure->isDone()) {
            $this->info("OK — chiusura eseguita in {$closure->elapsed_ms}ms (mode={$closure->agent_mode}).");
            return self::SUCCESS;
        }

        $this->error("FAIL — {$closure->last_error}");
        return self::FAILURE;
    }
}
