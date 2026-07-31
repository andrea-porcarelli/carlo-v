<?php

namespace App\Http\Controllers\Frontoffice;

use App\Http\Controllers\Controller;
use App\Models\OperationalIncident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espone il feed degli incidenti operativi al frontoffice.
 * Il frontend fa polling ogni ~15s per mostrare all'operatore le stampe/casse
 * fallite dietro le quinte (in coda) e le altre criticità non correlate a
 * un'azione sincrona appena eseguita.
 */
class OperationalIncidentController extends Controller
{
    private function verifyOperatorToken(?string $token): ?int
    {
        if (!$token) {
            return null;
        }
        $tokenData = session('operator_token_' . $token);
        if (!$tokenData || !isset($tokenData['user_id'])) {
            return null;
        }
        if (time() - ($tokenData['timestamp'] ?? 0) > 3600) {
            session()->forget('operator_token_' . $token);
            return null;
        }
        return $tokenData['user_id'];
    }

    /**
     * Feed incidenti non ancora acknowledged. Il frontend può passare
     * ?since=<id> per ottenere solo quelli nuovi rispetto all'ultimo visto.
     *
     * Non richiede token PIN: la sola visualizzazione degli incident non è
     * un'azione operativa. La protezione è delegata al BasicAuth del
     * frontoffice (già in atto sulla pagina che carica table-orders.js).
     * L'acknowledge invece richiede il token per attribuire chi ha letto.
     */
    public function unread(Request $request): JsonResponse
    {
        $since = (int) $request->query('since', 0);

        $incidents = OperationalIncident::unacknowledged()
            ->with('tableOrder.restaurantTable')
            ->when($since > 0, fn ($q) => $q->where('id', '>', $since))
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (OperationalIncident $i) {
                $tableRef = null;
                if ($i->table_order_id) {
                    $table = $i->tableOrder?->restaurantTable;
                    $tableRef = $table?->is_banco
                        ? 'Banco'
                        : ($table?->table_number ? "Tavolo {$table->table_number}" : "Ordine #{$i->table_order_id}");
                }

                return [
                    'id'               => $i->id,
                    'code'             => $i->code,
                    'severity'         => $i->severity,
                    'category'         => $i->category,
                    'operator_message' => $i->operator_message,
                    'technical_detail' => $i->technical_detail,
                    'context'          => $i->context,
                    'table_order_id'   => $i->table_order_id,
                    'table_ref'        => $tableRef,
                    'source'           => $i->source,
                    'created_at'       => $i->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'success'   => true,
            'incidents' => $incidents,
            'count'     => $incidents->count(),
        ]);
    }

    /**
     * Segna un incidente come letto. Se è presente un token operatore valido,
     * viene registrato chi ha letto (acknowledged_by); altrimenti l'ack è
     * anonimo — accettabile perché la pagina è già dietro BasicAuth.
     */
    public function acknowledge(Request $request, OperationalIncident $incident): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken($request->header('X-Operator-Token'));

        if ($incident->acknowledged_at === null) {
            $incident->forceFill([
                'acknowledged_at' => now(),
                'acknowledged_by' => $operatorId,
            ])->save();
        }

        return response()->json(['success' => true, 'incident_id' => $incident->id]);
    }
}
