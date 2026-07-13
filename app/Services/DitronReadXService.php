<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Esegue una Lettura X giornaliera (X-Report, non fiscale) sulla cassa
 * Ditron RT chiamando l'endpoint POST /read-x dell'agent.
 *
 * A differenza della chiusura Z, la lettura X non azzera i contatori e può
 * essere ripetuta più volte al giorno: per questo non persistiamo nulla su
 * DB, ci limitiamo al log su channel corrispettivi e a un DTO di ritorno.
 */
final class DitronReadXService
{
    private const LOG_CHANNEL = 'corrispettivi';

    /**
     * @return array{
     *     ok: bool,
     *     error: ?string,
     *     elapsed_ms: ?int,
     *     receipt_number: ?int,
     *     mode: ?string,
     *     raw_command: ?string,
     *     raw_err: ?string,
     * }
     */
    public function read(?int $operatorId = null): array
    {
        $baseUrl = (string) Setting::get('ditron_agent_url', '');
        if ($baseUrl === '') {
            $this->log('warning', 'ditron_agent_url non configurato', ['operator_id' => $operatorId]);
            return $this->fail('ditron_agent_url non configurato');
        }

        $token   = (string) Setting::get('ditron_agent_token', '');
        $timeout = (int) Setting::get('ditron_agent_timeout_seconds', 30);

        $idempotencyKey = 'read_x:' . now()->format('YmdHis') . ':' . Str::random(6);

        $this->log('info', 'Inizio Ditron Lettura X', [
            'operator_id'     => $operatorId,
            'idempotency_key' => $idempotencyKey,
        ]);

        $request = Http::timeout($timeout)->acceptJson();
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->post(rtrim($baseUrl, '/') . '/read-x', [
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (ConnectionException $e) {
            $error = 'connect_error: ' . Str::limit($e->getMessage(), 250);
            $this->log('warning', 'Ditron Lettura X fallita (connessione)', ['error' => $error]);
            return $this->fail($error);
        } catch (Throwable $e) {
            $error = 'unexpected_error: ' . Str::limit($e->getMessage(), 250);
            $this->log('warning', 'Ditron Lettura X fallita (eccezione)', ['error' => $error]);
            return $this->fail($error);
        }

        $body = $response->json();
        $isOk = $response->successful() && (bool) ($body['ok'] ?? false);

        $result = [
            'ok'             => $isOk,
            'error'          => $isOk ? null : ($body['error'] ?? ('http_' . $response->status())),
            'elapsed_ms'     => $body['elapsed_ms'] ?? null,
            'receipt_number' => $body['receipt_number'] ?? null,
            'mode'           => $body['mode'] ?? null,
            'raw_command'    => $body['raw_command'] ?? null,
            'raw_err'        => $body['raw_err'] ?? null,
        ];

        $this->log($isOk ? 'info' : 'warning', $isOk ? 'Ditron Lettura X eseguita' : 'Ditron Lettura X fallita', [
            'operator_id'     => $operatorId,
            'idempotency_key' => $idempotencyKey,
            'elapsed_ms'      => $result['elapsed_ms'],
            'receipt_number'  => $result['receipt_number'],
            'error'           => $result['error'],
        ]);

        return $result;
    }

    private function fail(string $error): array
    {
        return [
            'ok'             => false,
            'error'          => $error,
            'elapsed_ms'     => null,
            'receipt_number' => null,
            'mode'           => null,
            'raw_command'    => null,
            'raw_err'        => null,
        ];
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(self::LOG_CHANNEL)->log($level, '[DitronReadX] ' . $message, $context);
    }
}
