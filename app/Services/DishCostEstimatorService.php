<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\TableOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stima del costo materie prime per piatti / righe ordine / tavoli.
 *
 * Base di calcolo: media ponderata per unità base del materiale sui carichi in
 * `material_stocks`. Il `purchase_price` è per unità di fattura, quindi viene
 * normalizzato dividendo per `quantity_multiplier` della riga fattura d'origine
 * (supplier_invoice_products o external_invoice_lines) prima della ponderazione
 * per stock. Il consumo per piatto è preso dallo snapshot `order_item_materials`.
 *
 * Nota: è una **stima** — dipende dalla qualità dei dati di carico (unit_price
 * coerente con l'unità base del materiale).
 */
class DishCostEstimatorService
{
    /** @var array<int, float>|null cache in-memory dei costi medi per materiale */
    private ?array $avgCostsCache = null;

    /** @var array<string, bool>|null cache existence delle tabelle di join */
    private ?array $joinTablesCache = null;

    /**
     * Verifica quali tabelle di supporto per il calcolo del multiplier sono presenti.
     * @return array{sip:bool, eil:bool}
     */
    private function availableJoinTables(): array
    {
        if ($this->joinTablesCache !== null) {
            return $this->joinTablesCache;
        }
        $this->joinTablesCache = [
            'sip' => Schema::hasTable('supplier_invoice_products') && Schema::hasColumn('supplier_invoice_products', 'quantity_multiplier'),
            'eil' => Schema::hasTable('external_invoice_lines') && Schema::hasColumn('external_invoice_lines', 'quantity_multiplier'),
        ];
        return $this->joinTablesCache;
    }

    /**
     * Costruisce l'espressione SQL "prezzo effettivo per unità base".
     * Usa i join con le tabelle disponibili; senza tabelle di supporto ricade
     * sul purchase_price grezzo (retrocompatibile).
     */
    private function effectivePriceExpression(string $msAlias = 'ms'): string
    {
        $available = $this->availableJoinTables();
        $clauses = [];
        if ($available['sip']) {
            $clauses[] = "WHEN sip.quantity_multiplier IS NOT NULL AND sip.quantity_multiplier > 0 THEN {$msAlias}.purchase_price / sip.quantity_multiplier";
        }
        if ($available['eil']) {
            $clauses[] = "WHEN eil.quantity_multiplier IS NOT NULL AND eil.quantity_multiplier > 0 THEN {$msAlias}.purchase_price / eil.quantity_multiplier";
        }
        if (empty($clauses)) {
            return "{$msAlias}.purchase_price";
        }
        return 'CASE ' . implode(' ', $clauses) . " ELSE {$msAlias}.purchase_price END";
    }

    /**
     * Applica i LEFT JOIN opzionali per le tabelle di supporto al calcolo del multiplier.
     */
    private function applyMultiplierJoins($query, string $msAlias = 'ms')
    {
        $available = $this->availableJoinTables();
        if ($available['sip']) {
            $query->leftJoin('supplier_invoice_products as sip', 'sip.id', '=', "{$msAlias}.supplier_invoice_product_id");
        }
        if ($available['eil']) {
            $query->leftJoin('external_invoice_lines as eil', 'eil.id', '=', "{$msAlias}.external_invoice_line_id");
        }
        return $query;
    }

    /**
     * Subquery riusabile: material_id → avg_cost per unità base.
     */
    private function avgCostSubquery()
    {
        $q = DB::table('material_stocks as ms');
        $this->applyMultiplierJoins($q, 'ms');
        $eff = $this->effectivePriceExpression('ms');
        return $q
            ->select('ms.material_id', DB::raw("SUM(($eff) * ms.stock) / NULLIF(SUM(ms.stock), 0) as avg_cost"))
            ->whereNotNull('ms.purchase_price')
            ->where('ms.purchase_price', '>', 0)
            ->where('ms.stock', '>', 0)
            ->groupBy('ms.material_id');
    }

    /**
     * Costo medio per unità (base) di ogni materiale.
     *
     * `material_stocks.purchase_price` è salvato per unità di fattura (es. €/fusto),
     * mentre `stock` è già in unità base (es. cl). Serve dividere per il
     * `quantity_multiplier` della riga fattura d'origine per ottenere il prezzo
     * per unità base. Per i carichi manuali (senza fattura) manteniamo il
     * purchase_price così com'è.
     *
     * @return array<int, float> [material_id => avg_cost_per_base_unit]
     */
    public function getMaterialAvgCosts(): array
    {
        if ($this->avgCostsCache !== null) {
            return $this->avgCostsCache;
        }

        $effectivePrice = $this->effectivePriceExpression('ms');

        $query = DB::table('material_stocks as ms');
        $this->applyMultiplierJoins($query, 'ms');

        $rows = $query
            ->select('ms.material_id', DB::raw("SUM(($effectivePrice) * ms.stock) / NULLIF(SUM(ms.stock), 0) as avg_cost"))
            ->whereNotNull('ms.purchase_price')
            ->where('ms.purchase_price', '>', 0)
            ->where('ms.stock', '>', 0)
            ->groupBy('ms.material_id')
            ->pluck('avg_cost', 'ms.material_id');

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
     * Breakdown dettagliato del calcolo costo per una riga ordine.
     * Ritorna l'elenco dei materiali con costo unitario medio, quantità
     * per porzione, contributo alla riga (unità e totale linea).
     * Usato per il debug/tooltip in dettaglio vendita.
     *
     * @return array{unit_cost:float, line_cost:float, quantity:int, coverage:float, materials:array<int, array{material_id:int, name:string, unit:string, qty_per_portion:float, avg_cost:?float, contribution_unit:float, contribution_line:float, has_cost:bool}>}
     */
    public function getOrderItemCostBreakdown(OrderItem $item): array
    {
        $item->loadMissing('materials.material');

        $costs = $this->getMaterialAvgCosts();
        $quantity = (int) $item->quantity;
        $unitCost = 0.0;
        $materials = [];

        foreach ($item->materials as $m) {
            $avgCost   = $costs[$m->material_id] ?? null;
            $qtyPer    = (float) $m->quantity;
            $contribUnit = $avgCost !== null ? round($qtyPer * $avgCost, 4) : 0.0;
            $contribLine = round($contribUnit * $quantity, 4);
            $materials[] = [
                'material_id'       => $m->material_id,
                'name'              => $m->material?->label ?? "Materiale #{$m->material_id}",
                'unit'              => $m->material?->stock_type ?? '',
                'qty_per_portion'   => $qtyPer,
                'avg_cost'          => $avgCost,
                'contribution_unit' => $contribUnit,
                'contribution_line' => $contribLine,
                'has_cost'          => $avgCost !== null,
            ];
            if ($avgCost !== null) {
                $unitCost += $qtyPer * $avgCost;
            }
        }

        $total = count($materials);
        $known = count(array_filter($materials, fn($m) => $m['has_cost']));

        return [
            'unit_cost' => round($unitCost, 4),
            'line_cost' => round($unitCost * $quantity, 4),
            'quantity'  => $quantity,
            'coverage'  => $total > 0 ? $known / $total : 0.0,
            'materials' => $materials,
        ];
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
            ->joinSub($this->avgCostSubquery(), 'mc', 'oim.material_id', '=', 'mc.material_id')
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
            ->joinSub($this->avgCostSubquery(), 'mc', 'oim.material_id', '=', 'mc.material_id')
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
