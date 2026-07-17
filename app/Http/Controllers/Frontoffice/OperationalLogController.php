<?php

namespace App\Http\Controllers\Frontoffice;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Models\TableOrder;
use App\Models\TableOrderLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pin = $request->header('X-Admin-Pin') ?? $request->input('pin');
        if (!$pin || !User::where('role', 'admin')->where('authentication_pin', $pin)->exists()) {
            return response()->json(['success' => false, 'message' => 'Non autorizzato'], 401);
        }

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))->toDateString()
            : Carbon::today()->toDateString();

        return response()->json([
            'date'        => $date,
            'venduto'     => $this->venduto($date),
            'daIncassare' => $this->daIncassare(),
            'cancellati'  => $this->cancellati($date),
            'modificati'  => $this->modificati($date),
        ]);
    }

    private function venduto(string $date): array
    {
        $paid = TableOrder::where('status', 'paid')
            ->whereDate('closed_at', $date)
            ->with('restaurantTable:id,table_number,is_banco')
            ->get(['id', 'restaurant_table_id', 'total_amount', 'discount_type', 'discount_value', 'payment_method', 'autoconsumo', 'closed_at']);

        $buckets = [
            'contanti'            => 0.0,
            'pos'                 => 0.0,
            'fatture'             => 0.0,
            'autoconsumo'         => 0.0,
            'chiusure_conto'      => 0.0,
            'vendite_banco'       => 0.0,
        ];

        $details = [
            'contanti'       => [],
            'pos'            => [],
            'fatture'        => [],
            'autoconsumo'    => [],
            'chiusure_conto' => [],
            'vendite_banco'  => [],
        ];

        $scontriniCount = 0;
        $fattureCount   = 0;

        foreach ($paid as $order) {
            $amount = $order->hasDiscount() ? $order->getDiscountedTotal() : (float) $order->total_amount;
            $table  = $order->restaurantTable;
            $isBanco = (bool) $table?->is_banco;
            $entry = [
                'table_number' => $isBanco ? 'BANCO' : ($table?->table_number ?? '-'),
                'amount'       => round($amount, 2),
                'closed_at'    => $order->closed_at?->format('H:i'),
            ];

            if ($isBanco) {
                $buckets['vendite_banco'] += $amount;
                $details['vendite_banco'][] = $entry;
            }

            if ($order->autoconsumo) {
                $buckets['autoconsumo'] += $amount;
                $details['autoconsumo'][] = $entry;
                continue;
            }

            switch ($order->payment_method) {
                case 'contanti':
                    $buckets['contanti']  += $amount;
                    $details['contanti'][] = $entry;
                    $scontriniCount++;
                    break;
                case 'pos':
                    $buckets['pos']       += $amount;
                    $details['pos'][] = $entry;
                    $scontriniCount++;
                    break;
                case 'fattura':
                case 'fattura_contanti':
                case 'fattura_pos':
                case 'bonifico':
                case 'assegno':
                case 'misto':
                    $buckets['fatture'] += $amount;
                    $details['fatture'][] = $entry;
                    $fattureCount++;
                    break;
                case 'chiusura_conto':
                    $buckets['chiusure_conto'] += $amount;
                    $details['chiusure_conto'][] = $entry;
                    break;
            }
        }

        // Sort each detail list by amount descending
        foreach ($details as &$list) {
            usort($list, fn($a, $b) => strcmp($b['closed_at'] ?? '', $a['closed_at'] ?? ''));
        }
        unset($list);

        $totaleIncassato = $buckets['contanti'] + $buckets['pos'] + $buckets['fatture'];

        return array_map(fn($v) => round($v, 2), $buckets) + [
            'totale_incassato' => round($totaleIncassato, 2),
            'scontrini_count'  => $scontriniCount,
            'fatture_count'    => $fattureCount,
            'dettagli'         => $details,
        ];
    }

    private function daIncassare(): array
    {
        $openOrders = TableOrder::where('status', 'open')
            ->with(['restaurantTable:id,table_number,is_banco'])
            ->orderBy('opened_at')
            ->get(['id', 'restaurant_table_id', 'total_amount', 'discount_type', 'discount_value', 'opened_at']);

        $list = $openOrders->map(function ($o) {
            $t = $o->restaurantTable;
            $isBanco = (bool) ($t?->is_banco);
            $amount  = $o->hasDiscount() ? $o->getDiscountedTotal() : (float) $o->total_amount;
            return [
                'table_number' => $isBanco ? 'BANCO' : ($t?->table_number ?? '-'),
                'is_banco'     => $isBanco,
                'amount'       => round($amount, 2),
                'opened_at'    => optional($o->opened_at)->format('H:i'),
            ];
        })
        ->filter(fn($r) => !$r['is_banco'] || $r['amount'] > 0)
        ->values();

        return [
            'totale' => round($list->sum('amount'), 2),
            'tavoli' => $list,
        ];
    }

    private function cancellati(string $date): array
    {
        return TableOrderLog::where('action', TableOrderLog::ACTION_REMOVE_ITEM)
            ->whereDate('created_at', $date)
            ->with(['user:id,name', 'tableOrder.restaurantTable:id,table_number'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($log) => [
                'time'     => $log->created_at->format('H:i'),
                'operator' => $log->user?->name ?? '-',
                'table'    => $log->tableOrder?->restaurantTable?->table_number ?? '-',
                'dish'     => $log->data_before['dish_name'] ?? '-',
                'qty'      => $log->data_before['quantity'] ?? '-',
                'price'    => isset($log->data_before['unit_price'])
                    ? number_format((float) $log->data_before['unit_price'], 2, ',', '.')
                    : null,
                'reason'   => $log->data_before['removal_reason']
                    ?? $log->notes
                    ?? '-',
            ])
            ->toArray();
    }

    private function modificati(string $date): array
    {
        $actions = [
            'update_item_price',
            TableOrderLog::ACTION_UPDATE_ITEM_QUANTITY,
            TableOrderLog::ACTION_UPDATE_ITEM,
            TableOrderLog::ACTION_APPLY_DISCOUNT,
        ];

        $relevantFields = ['quantity', 'unit_price', 'subtotal', 'discount_amount', 'discount_type', 'discount_value'];

        $rows = [];
        TableOrderLog::whereIn('action', $actions)
            ->whereDate('created_at', $date)
            ->with(['user:id,name', 'tableOrder.restaurantTable:id,table_number'])
            ->orderByDesc('created_at')
            ->get()
            ->each(function ($log) use (&$rows, $relevantFields) {
                $time     = $log->created_at->format('H:i');
                $operator = $log->user?->name ?? '-';
                $table    = $log->tableOrder?->restaurantTable?->table_number ?? '-';
                $dish     = $log->data_before['dish_name']
                    ?? $log->data_after['dish_name']
                    ?? '-';

                // Discount changes live at order level (no dish); emit a single row
                if ($log->action === TableOrderLog::ACTION_APPLY_DISCOUNT) {
                    $old = $log->data_before['discount_amount'] ?? 0;
                    $new = $log->data_after['discount_amount'] ?? ($log->changes['discount_amount']['new'] ?? 0);
                    $rows[] = [
                        'time'     => $time,
                        'operator' => $operator,
                        'table'    => $table,
                        'dish'     => '-',
                        'field'    => 'Sconto',
                        'old'      => '€' . number_format((float) $old, 2, ',', '.'),
                        'new'      => '€' . number_format((float) $new, 2, ',', '.'),
                    ];
                    return;
                }

                $changes = is_array($log->changes) ? $log->changes : [];
                foreach ($changes as $field => $change) {
                    if (!in_array($field, $relevantFields, true)) continue;
                    $old = $change['old'] ?? null;
                    $new = $change['new'] ?? null;
                    if ($old === $new) continue;

                    if (in_array($field, ['unit_price', 'subtotal', 'discount_amount', 'discount_value'], true)) {
                        $old = is_numeric($old) ? '€' . number_format((float) $old, 2, ',', '.') : ($old ?? '-');
                        $new = is_numeric($new) ? '€' . number_format((float) $new, 2, ',', '.') : ($new ?? '-');
                    }

                    $rows[] = [
                        'time'     => $time,
                        'operator' => $operator,
                        'table'    => $table,
                        'dish'     => $dish,
                        'field'    => $this->translateField($field),
                        'old'      => $old ?? '-',
                        'new'      => $new ?? '-',
                    ];
                }
            });

        return $rows;
    }

    private function translateField(string $field): string
    {
        return [
            'quantity'        => 'Quantità',
            'unit_price'      => 'Prezzo unitario',
            'subtotal'        => 'Subtotale',
            'discount_amount' => 'Sconto',
            'discount_type'   => 'Tipo sconto',
            'discount_value'  => 'Valore sconto',
        ][$field] ?? ucfirst(str_replace('_', ' ', $field));
    }
}