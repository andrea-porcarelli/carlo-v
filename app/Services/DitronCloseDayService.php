<?php

namespace App\Services;

use App\Models\DitronDailyClosure;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Esegue la chiusura giornaliera fiscale (Z-report) sulla cassa Ditron RT
 * chiamando l'endpoint POST /close-day dell'agent.
 *
 * Idempotenza: una sola riga per closure_date. Se esiste già con status=done
 * la giornata non viene richiamata.
 */
final class DitronCloseDayService
{
    private const LOG_CHANNEL = 'corrispettivi';

    public function close(
        ?CarbonInterface $date = null,
        string $source = DitronDailyClosure::SOURCE_AUTO,
        ?int $operatorId = null
    ): DitronDailyClosure {
        $date ??= Carbon::today();
        $dateStr = $date->toDateString();

        $existing = DitronDailyClosure::where('closure_date', $dateStr)->first();
        if ($existing && $existing->isDone()) {
            $this->log('info', 'Ditron Z già eseguita per la giornata', $existing->getLogContext());
            return $existing;
        }

        $tipo = (int) Setting::get('ditron_close_day_tipo', 2);

        $closure = $existing ?? DitronDailyClosure::create([
            'closure_date'    => $dateStr,
            'source'          => $source,
            'status'          => DitronDailyClosure::STATUS_PENDING,
            'tipo'            => $tipo,
            'idempotency_key' => 'close_day:' . $dateStr,
            'operator_id'     => $operatorId,
        ]);

        if ($existing) {
            $closure->update([
                'source'      => $source,
                'operator_id' => $operatorId ?? $closure->operator_id,
                'tipo'        => $tipo,
                'last_error'  => null,
            ]);
        }

        $this->log('info', 'Inizio Ditron Z giornaliera', $closure->getLogContext() + ['tipo' => $tipo]);

        $this->dispatch($closure);

        $fresh = $closure->refresh();
        $this->notifyTelegram($fresh);

        return $fresh;
    }

    private function dispatch(DitronDailyClosure $closure): void
    {
        $baseUrl = (string) Setting::get('ditron_agent_url', '');
        if ($baseUrl === '') {
            $closure->update([
                'status'     => DitronDailyClosure::STATUS_FAILED,
                'attempts'   => $closure->attempts + 1,
                'last_error' => 'ditron_agent_url non configurato',
            ]);
            return;
        }

        $closure->update([
            'status'   => DitronDailyClosure::STATUS_SENDING,
            'attempts' => $closure->attempts + 1,
        ]);

        $token   = (string) Setting::get('ditron_agent_token', '');
        $timeout = (int) Setting::get('ditron_agent_timeout_seconds', 30);

        $request = Http::timeout($timeout)->acceptJson();
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $payload = [
            'idempotency_key' => $closure->idempotency_key,
            'tipo'            => (int) $closure->tipo,
        ];

        try {
            $response = $request->post(rtrim($baseUrl, '/') . '/close-day', $payload);
        } catch (ConnectionException $e) {
            $closure->update([
                'status'     => DitronDailyClosure::STATUS_FAILED,
                'last_error' => 'connect_error: ' . Str::limit($e->getMessage(), 250),
            ]);
            return;
        } catch (Throwable $e) {
            $closure->update([
                'status'     => DitronDailyClosure::STATUS_FAILED,
                'last_error' => 'unexpected_error: ' . Str::limit($e->getMessage(), 250),
            ]);
            return;
        }

        $body = $response->json();
        $isOk = $response->successful() && (bool) ($body['ok'] ?? false);

        $closure->update([
            'status'         => $isOk ? DitronDailyClosure::STATUS_DONE : DitronDailyClosure::STATUS_FAILED,
            'receipt_number' => $body['receipt_number'] ?? null,
            'raw_command'    => $body['raw_command'] ?? null,
            'raw_err'        => $body['raw_err'] ?? null,
            'elapsed_ms'     => $body['elapsed_ms'] ?? null,
            'agent_mode'     => $body['mode'] ?? null,
            'last_error'     => $isOk ? null : ($body['error'] ?? ('http_' . $response->status())),
            'sent_at'        => $isOk ? now() : null,
        ]);

        $this->log($isOk ? 'info' : 'warning', $isOk ? 'Ditron Z eseguita' : 'Ditron Z fallita', $closure->getLogContext() + [
            'status'     => $closure->status,
            'elapsed_ms' => $closure->elapsed_ms,
        ]);
    }

    private function notifyTelegram(DitronDailyClosure $closure): void
    {
        if (!config('logging.channels.telegram.handler_with.apiKey')) {
            return;
        }

        $date        = $closure->closure_date?->format('d/m/Y') ?? '—';
        $sourceLabel = $closure->source === DitronDailyClosure::SOURCE_AUTO ? 'automatica (23:59)' : 'manuale (backoffice)';
        $elapsed     = $closure->elapsed_ms !== null ? number_format($closure->elapsed_ms / 1000, 2, ',', '.') . 's' : '—';
        $mode        = $closure->agent_mode ?: '—';

        try {
            if ($closure->isDone()) {
                $msg  = "✅ <b>Chiusura Ditron</b> — {$date}\n";
                $msg .= "Fonte: {$sourceLabel}\n";
                $msg .= "Modalità: {$mode}\n";
                $msg .= "Durata: {$elapsed}";
                if ($closure->receipt_number) {
                    $msg .= "\nProgressivo agent: {$closure->receipt_number}";
                }
                Log::channel('telegram')->info($msg);
            } else {
                $err = $closure->last_error ?: 'errore sconosciuto';
                $msg  = "❌ <b>Chiusura Ditron fallita</b> — {$date}\n";
                $msg .= "Fonte: {$sourceLabel}\n";
                $msg .= "Modalità: {$mode}\n";
                $msg .= "Errore: <code>" . Str::limit($err, 400) . "</code>";
                Log::channel('telegram')->warning($msg);
            }
        } catch (Throwable $e) {
            Log::warning('Notifica Telegram chiusura Ditron fallita', ['error' => $e->getMessage()]);
        }
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(self::LOG_CHANNEL)->log($level, '[DitronCloseDay] ' . $message, $context);
    }
}
