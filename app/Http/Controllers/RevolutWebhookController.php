<?php

namespace App\Http\Controllers;

use App\Models\TableOrder;
use App\Services\RevolutPaymentCloser;
use App\Services\RevolutTerminalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Riceve gli eventi webhook da Revolut Merchant API.
 *
 * Endpoint: POST /webhook/revolut
 * - CSRF-exempt (configurato in bootstrap/app.php tramite il pattern 'webhook/*')
 * - Valida firma HMAC SHA-256 col secret 'revolut.webhook_secret'
 * - Idempotente: la chiusura dell'ordine usa lockForUpdate (vedi RevolutPaymentCloser)
 * - Risponde sempre 200 dopo aver loggato, per non far ritentare Revolut
 *   indefinitamente in caso di errori applicativi (la verità sullo stato è
 *   comunque recuperabile via polling).
 */
class RevolutWebhookController extends Controller
{
    public function handle(
        Request $request,
        RevolutTerminalService $revolut,
        RevolutPaymentCloser $closer
    ): JsonResponse {
        $rawBody   = $request->getContent();
        $signature = (string) $request->header('Revolut-Signature', '');
        $timestamp = (string) $request->header('Revolut-Request-Timestamp', '');

        if (!$revolut->verifyWebhookSignature($rawBody, $signature, $timestamp)) {
            Log::warning('Revolut webhook: firma non valida', [
                'signature' => $signature,
                'timestamp' => $timestamp,
                'body_len'  => strlen($rawBody),
            ]);
            return response()->json(['ok' => false, 'message' => 'invalid signature'], 401);
        }

        $payload   = $request->json()->all();
        $eventType = (string) ($payload['event'] ?? $payload['event_type'] ?? '');
        $orderId   = (string) ($payload['order_id'] ?? $payload['data']['id'] ?? $payload['id'] ?? '');

        Log::info('Revolut webhook ricevuto', [
            'event'    => $eventType,
            'order_id' => $orderId,
        ]);

        if ($orderId === '') {
            return response()->json(['ok' => true, 'note' => 'no order_id in payload']);
        }

        $tableOrder = TableOrder::where('revolut_order_id', $orderId)->first();
        if (!$tableOrder) {
            Log::warning('Revolut webhook: nessun TableOrder per revolut_order_id', ['order_id' => $orderId]);
            return response()->json(['ok' => true, 'note' => 'order not found locally']);
        }

        $event = strtoupper($eventType);

        // Eventi di pagamento completato → chiudi l'ordine
        if (in_array($event, ['ORDER_COMPLETED', 'ORDER_AUTHORISED', 'ORDER_PAYMENT_COMPLETED'], true)) {
            $closer->closeAfterPayment($tableOrder, strtolower($event));
            return response()->json(['ok' => true]);
        }

        // Eventi di fallimento / annullo → riporta l'ordine in 'open' se è ancora pending
        if (in_array($event, ['ORDER_PAYMENT_FAILED', 'ORDER_PAYMENT_DECLINED', 'ORDER_CANCELLED'], true)) {
            if ($tableOrder->isPendingPayment()) {
                $tableOrder->update([
                    'status'                     => 'open',
                    'revolut_order_id'           => null,
                    'revolut_payment_state'      => strtolower($event),
                    'revolut_payment_started_at' => null,
                    'revolut_operator_id'        => null,
                ]);
            }
            return response()->json(['ok' => true]);
        }

        // Evento ignorato (es. ORDER_CREATED) — solo log, nessuna azione
        return response()->json(['ok' => true, 'note' => 'event ignored']);
    }
}
