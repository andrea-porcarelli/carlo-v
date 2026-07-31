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
     */
    public function unread(Request $request): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken($request->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        $since = (int) $request->query('since', 0);

        $incidents = OperationalIncident::unacknowledged()
            ->when($since > 0, fn ($q) => $q->where('id', '>', $since))
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (OperationalIncident $i) => [
                'id'               => $i->id,
                'code'             => $i->code,
                'severity'         => $i->severity,
                'category'         => $i->category,
                'operator_message' => $i->operator_message,
                'technical_detail' => $i->technical_detail,
                'context'          => $i->context,
                'table_order_id'   => $i->table_order_id,
                'source'           => $i->source,
                'created_at'       => $i->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success'   => true,
            'incidents' => $incidents,
            'count'     => $incidents->count(),
        ]);
    }

    /**
     * Segna un incidente come letto dall'operatore corrente.
     */
    public function acknowledge(Request $request, OperationalIncident $incident): JsonResponse
    {
        $operatorId = $this->verifyOperatorToken($request->header('X-Operator-Token'));
        if (!$operatorId) {
            return response()->json(['success' => false, 'message' => 'Token operatore non valido'], 401);
        }

        if ($incident->acknowledged_at === null) {
            $incident->forceFill([
                'acknowledged_at' => now(),
                'acknowledged_by' => $operatorId,
            ])->save();
        }

        return response()->json(['success' => true, 'incident_id' => $incident->id]);
    }
}
