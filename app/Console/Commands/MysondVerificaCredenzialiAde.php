<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\MysondFatturaService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class MysondVerificaCredenzialiAde extends Command
{
    protected $signature = 'mysond:verifica-ade';

    protected $description = 'Verifica le credenziali Fisconline/Entratel tramite WSC4 (MySond) e notifica in caso di problemi';

    public function handle(MysondFatturaService $service): int
    {
        $now = Carbon::now();

        try {
            $result = $service->verificaCredenzialiAde();
        } catch (Throwable $e) {
            Log::error('verificaCredenzialiAde: eccezione SOAP', ['ex' => $e->getMessage()]);
            $this->persist($now, null, null, null, 'error', $e->getMessage());
            $this->notifyTelegram('error', 'Eccezione durante la verifica', $e->getMessage());
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $esito       = isset($result->esito) ? (int) $result->esito : null;
        $codice      = $result->codice ?? null;
        $descrizione = $result->descrizione ?? ($result->messaggio ?? null);

        $status = match (true) {
            $esito === 0                              => 'ok',
            stripos((string) $codice, 'attiva') !== false
                || stripos((string) $descrizione, 'acquistare il pacchetto') !== false
                                                      => 'warning',
            default                                   => 'error',
        };

        $this->persist($now, $esito, $codice, $descrizione, $status, null);

        $this->line("Esito: {$esito} | Codice: {$codice} | Descrizione: {$descrizione}");

        if ($status !== 'ok') {
            $title = $status === 'warning'
                ? 'Pacchetto MySond non attivo'
                : ($descrizione ?? 'Credenziali AdE da verificare');
            $this->notifyTelegram($status, $title, ($descrizione ?: '') . "\nCodice: {$codice}\nEsito: {$esito}");
            $this->warn("Stato: {$status}.");
        } else {
            $this->info('Credenziali AdE: OK.');
        }

        return self::SUCCESS;
    }

    private function persist(Carbon $at, ?int $esito, ?string $codice, ?string $descrizione, string $status, ?string $exception): void
    {
        Setting::set('agenzia_entrate.check_last_at', $at->toIso8601String());
        Setting::set('agenzia_entrate.check_last_esito', $esito ?? '');
        Setting::set('agenzia_entrate.check_last_codice', $codice ?? '');
        Setting::set('agenzia_entrate.check_last_descrizione', $descrizione ?? '');
        Setting::set('agenzia_entrate.check_last_status', $status);
        Setting::set('agenzia_entrate.check_last_exception', $exception ?? '');
    }

    private function notifyTelegram(string $status, string $title, string $details): void
    {
        if (!config('logging.channels.telegram.handler_with.apiKey')) {
            return;
        }

        $icon = $status === 'error' ? '🚨' : '⚠️';
        $msg  = "{$icon} <b>Credenziali Agenzia Entrate</b>\n\n"
              . "<b>" . e($title) . "</b>\n"
              . e($details);

        Log::channel('telegram')->warning($msg);
    }
}
