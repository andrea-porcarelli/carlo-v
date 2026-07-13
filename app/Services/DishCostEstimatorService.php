<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\TableOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stima del costo materie prime per piatti / righe ordine / tavoli.
 *
 * Base di calcolo: costo medio ponderato per materiale (SUM(purchase_price * stock) / SUM(stock))
 * calcolato sui carichi in material_stocks. Il consumo per piatto è preso dallo
 * snapshot `order_item_materials` (quantità in unità base del materiale).
 *
 * Nota: è una **stima** — dipende dalla qualità dei dati di carico (unit_price
 * coerente con l'unità base del materiale).
 */
class DishCostEstimatorService
{
    /** @var array<int, float>|null cache in-memory dei costi medi per materiale */
    private ?array $avgCostsCache = null;

    /**
     * Costo medio per unità (base) di ogni materiale.
     * @return array<int, float> [material_id => avg_cost_per_base_unit]
     */
    public function getMaterialAvgCosts(): array
    {
        if ($this->avgCostsCache !== null) {
            return $this->avgCostsCache;
        }

        $rows = DB::table('material_stocks')
            ->select('material_id', DB::raw('SUM(purchase_price * stock) / NULLIF(SUM(stock), 0) as avg_cost'))
            ->whereNotNull('purchase_price')
            ->where('purchase_price', '>', 0)
            ->where('stock', '>', 0)
            ->groupBy('material_id')
            ->pluck('avg_cost', 'material_id');

        $this->avgCostsCache = $rows->map(fn($v) => (float) $v)->toArray();
        return $this->avgCostsCache;
    }

    /**
     * Stima costo unitario (per singola porzione) della riga ordine.
     * Usa lo snapshot order_item_materials.
     */
    public function estimateOrderItemUnitCost(OrderItem $item): float
    {
        if ($item->isSegueItem() || !$item->relationLoaded('materials')) {
            $item->loadMissing('materials');
        }
        if ($item->isSegueItem()) {
            return 0.0;
        }

        $costs = $this->getMaterialAvgCosts();
        $total = 0.0;
        foreach ($item->materials as $m) {
            $unitCost = $costs[$m->material_id] ?? null;
            if ($unitCost === null) {
                continue;
            }
            $total += (float) $m->quantity * $unitCost;
        }
        return round($total, 4);
    }

    /**
     * Stima costo totale della riga (unit_cost × quantity).
     */
    public function estimateOrderItemCost(OrderItem $item): float
    {
        return round($this->estimateOrderItemUnitCost($item) * (int) $item->quantity, 4);
    }

    /**
     * Percentuale di materiali della riga di cui abbiamo un costo (0..1).
     * Usata per segnalare stime parziali/mancanti.
     */
    public function orderItemCostCoverage(OrderItem $item): float
    {
        $item->loadMissing('materials');
        $total = $item->materials->count();
        if ($total === 0) {
            return 0.0;
        }
        $costs = $this->getMaterialAvgCosts();
        $known = $item->materials->filter(fn($m) => isset($costs[$m->material_id]))->count();
        return $known / $total;
    }

    /**
     * Stima costo totale del tavolo (somma stime righe non cancellate).
     */
    public function estimateOrderCost(TableOrder $order): float
    {
        $order->loadMissing('items.materials');
        $total = 0.0;
        foreach ($order->items as $item) {
            if ($item->status === 'cancelled' || $item->trashed()) {
                continue;
            }
            $total += $this->estimateOrderItemCost($item);
        }
        return round($total, 2);
    }

    /**
     * Aggregato costo stimato per periodo (chiusi, autoconsumo escluso).
     * Ottimizzato: un'unica query con subquery costi medi.
     *
     * @param string|null $from data (Y-m-d) inclusa
     * @param string|null $to   data (Y-m-d) inclusa
     */
    public function estimateCostForPeriod(?string $from = null, ?string $to = null): float
    {
        $q = DB::table('order_items as oi')
            ->join('order_item_materials as oim', 'oi.id', '=', 'oim.order_item_id')
            ->join('table_orders as too', 'oi.table_order_id', '=', 'too.id')
            ->joinSub(
                DB::table('material_stocks')
                    ->select('material_id', DB::raw('SUM(purchase_price * stock) / NULLIF(SUM(stock), 0) as avg_cost'))
                    ->whereNotNull('purchase_price')
                    ->where('purchase_price', '>', 0)
                    ->where('stock', '>', 0)
                    ->groupBy('material_id'),
                'mc',
                'oim.material_id',
                '=',
                'mc.material_id'
            )
            ->where('too.status', 'paid')
            ->where(fn($q) => $q->where('too.autoconsumo', false)->orWhereNull('too.autoconsumo'))
            ->whereNull('oi.deleted_at');

        if ($from) {
            $q->whereDate('too.closed_at', '>=', $from);
        }
        if ($to) {
            $q->whereDate('too.closed_at', '<=', $to);
        }

        $val = $q->sum(DB::raw('oi.quantity * oim.quantity * mc.avg_cost'));
        return round((float) $val, 2);
    }

    /**
     * Wrapper: costi stimati per un set di intervalli, restituisce array associativo.
     * @param array<string, array{from: ?string, to: ?string}> $periods
     * @return array<string, float>
     */
    public function estimateCostsForPeriods(array $periods): array
    {
        $out = [];
        foreach ($periods as $key => $range) {
            $out[$key] = $this->estimateCostForPeriod($range['from'] ?? null, $range['to'] ?? null);
        }
        return $out;
    }

    /**
     * Costo stimato aggregato per dish_id (tutti gli ordini pagati, autoconsumo escluso).
     * @param array<int>|null $dishIds se null restituisce tutti
     * @return array<int, float> [dish_id => total_cost]
     */
    public function estimateCostsByDish(?array $dishIds = null): array
    {
        $q = DB::table('order_items as oi')
            ->join('order_item_materials as oim', 'oi.id', '=', 'oim.order_item_id')
            ->join('table_orders as too', 'oi.table_order_id', '=', 'too.id')
            ->joinSub(
                DB::table('material_stocks')
                    ->select('material_id', DB::raw('SUM(purchase_price * stock) / NULLIF(SUM(stock), 0) as avg_cost'))
                    ->whereNotNull('purchase_price')
                    ->where('purchase_price', '>', 0)
                    ->where('stock', '>', 0)
                    ->groupBy('material_id'),
                'mc',
                'oim.material_id',
                '=',
                'mc.material_id'
            )
            ->where('too.status', 'paid')
            ->where(fn($q) => $q->where('too.autoconsumo', false)->orWhereNull('too.autoconsumo'))
            ->whereNull('oi.deleted_at')
            ->whereNotNull('oi.dish_id');

        if ($dishIds !== null) {
            $q->whereIn('oi.dish_id', $dishIds);
        }

        return $q->select('oi.dish_id', DB::raw('SUM(oi.quantity * oim.quantity * mc.avg_cost) as total_cost'))
            ->groupBy('oi.dish_id')
            ->pluck('total_cost', 'oi.dish_id')
            ->map(fn($v) => round((float) $v, 2))
            ->toArray();
    }
}
