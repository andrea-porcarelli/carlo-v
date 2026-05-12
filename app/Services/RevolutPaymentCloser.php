<?php

namespace App\Services;

use App\Models\TableOrder;
use App\Models\TableOrderCorrispettivo;
use Illuminate\Support\Facades\DB;

/**
 * Chiude un TableOrder pagato via Revolut Terminal in modo idempotente.
 *
 * Usato da:
 *  - RevolutWebhookController (evento ORDER_COMPLETED)
 *  - TableOrderController::posPayStatus (polling fallback)
 *  - TableOrderController::posPayCancel (race-condition: cancel arrivato dopo il pagamento)
 *
 * Il row lock dentro la transazione garantisce che chiamate concorrenti producano
 * un solo close + un solo corrispettivo.
 */
class RevolutPaymentCloser
{
    public function __construct(
        private TableOrderLoggerService $logger,
        private CorrispettivoService $corrispettivoService,
    ) {}

    /**
     * @return array{total_paid:float, corrispettivo:?TableOrderCorrispettivo, already_done:bool}
     */
    public function closeAfterPayment(TableOrder $order, string $revolutState): array
    {
        $result = DB::transaction(function () use ($order, $revolutState) {
            $fresh = TableOrder::lockForUpdate()->find($order->id);
            if (!$fresh) {
                return ['total_paid' => 0.0, 'order' => null, 'operator_id' => null, 'already_done' => true];
            }
            if ($fresh->status === 'paid') {
                return ['total_paid' => (float) $fresh->total_amount, 'order' => null, 'operator_id' => null, 'already_done' => true];
            }

            $operatorId = $fresh->revolut_operator_id ?? $fresh->waiter_id;

            $fresh->update([
                'revolut_payment_state' => $revolutState,
                'preconto_requested_at' => null,
            ]);
            $this->logger->logPayOrder($fresh, 'pos', $operatorId);
            $this->logger->logCloseOrder($fresh, $operatorId);
            $fresh->close('pos');

            return [
                'total_paid'   => (float) $fresh->total_amount,
                'order'        => $fresh,
                'operator_id'  => $operatorId,
                'already_done' => false,
            ];
        });

        if ($result['already_done']) {
            return [
                'total_paid'    => $result['total_paid'],
                'corrispettivo' => null,
                'already_done'  => true,
            ];
        }

        $corrispettivo = $this->corrispettivoService->emettiPerOrdine(
            $result['order'],
            'pos',
            $result['operator_id']
        );

        return [
            'total_paid'    => $result['total_paid'],
            'corrispettivo' => $corrispettivo,
            'already_done'  => false,
        ];
    }
}
