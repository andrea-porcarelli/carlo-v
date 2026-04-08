<?php

namespace App\Services;

use App\Models\Material;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Calcola le giacenze per tutti i materiali
     * @return Collection [material_id => ['material' => Material, 'imported' => float, 'consumed' => float, 'current' => float, 'is_low' => bool]]
     */
    public function calculateAllStocks(): Collection
    {
        $materials = Material::all();
        $importedByMaterial = $this->getAllImported();
        $consumedByMaterial = $this->getAllConsumed();

        return $materials->mapWithKeys(function ($material) use ($importedByMaterial, $consumedByMaterial) {
            $imported = $importedByMaterial[$material->id] ?? 0;
            $consumed = $consumedByMaterial[$material->id] ?? 0;
            $current = $imported - $consumed;

            return [
                $material->id => [
                    'material' => $material,
                    'imported' => $imported,
                    'consumed' => $consumed,
                    'current' => $current,
                    'is_low' => $material->isLowStock($current),
                ]
            ];
        });
    }

    /**
     * Calcola la giacenza per un singolo materiale
     */
    public function calculateStock(Material $material): array
    {
        $imported = $this->getTotalImported($material->id);
        $consumed = $this->getTotalConsumed($material->id);
        $current = $imported - $consumed;

        return [
            'material' => $material,
            'imported' => $imported,
            'consumed' => $consumed,
            'current' => $current,
            'is_low' => $material->isLowStock($current),
        ];
    }

    /**
     * Ottiene il totale importato per un materiale
     * SUM(material_stocks.stock) WHERE material_id = ?
     */
    public function getTotalImported(int $materialId): float
    {
        return (float) DB::table('material_stocks')
            ->where('material_id', $materialId)
            ->sum('stock');
    }

    /**
     * Ottiene il totale consumato per un materiale
     * SUM(order_items.quantity * dish_materials.quantity)
     * WHERE table_orders.status = 'closed'
     */
    public function getTotalConsumed(int $materialId): float
    {
        return (float) DB::table('order_items')
            ->join('dishes', 'order_items.dish_id', '=', 'dishes.id')
            ->join('dish_materials', 'dishes.id', '=', 'dish_materials.dish_id')
            ->join('table_orders', 'order_items.table_order_id', '=', 'table_orders.id')
            ->where('table_orders.status', 'paid')
            ->where('dish_materials.material_id', $materialId)
            ->whereNull('order_items.deleted_at')
            ->sum(DB::raw('order_items.quantity * dish_materials.quantity'));
    }

    /**
     * Ottiene tutte le movimentazioni (carichi + consumi) per un materiale, ordinate per data desc
     */
    public function getMovements(int $materialId): Collection
    {
        // Carichi (material_stocks)
        $loads = DB::table('material_stocks')
            ->leftJoin('supplier_invoice_products', 'material_stocks.supplier_invoice_product_id', '=', 'supplier_invoice_products.id')
            ->where('material_stocks.material_id', $materialId)
            ->select(
                'material_stocks.id',
                'material_stocks.created_at as date',
                'material_stocks.stock as quantity',
                'material_stocks.purchase_date',
                'material_stocks.purchase_price',
                'material_stocks.notes',
                'supplier_invoice_products.product_name as invoice_product',
                DB::raw("'load' as type")
            )
            ->get();

        // Consumi (order_items tramite dish_materials)
        $consumptions = DB::table('order_items')
            ->join('dishes', 'order_items.dish_id', '=', 'dishes.id')
            ->join('dish_materials', 'dishes.id', '=', 'dish_materials.dish_id')
            ->join('table_orders', 'order_items.table_order_id', '=', 'table_orders.id')
            ->join('restaurant_tables', 'table_orders.restaurant_table_id', '=', 'restaurant_tables.id')
            ->where('table_orders.status', 'paid')
            ->where('dish_materials.material_id', $materialId)
            ->whereNull('order_items.deleted_at')
            ->select(
                'order_items.id',
                'order_items.created_at as date',
                DB::raw('(order_items.quantity * dish_materials.quantity) as quantity'),
                'dishes.label as dish_name',
                'restaurant_tables.table_number as table_name',
                'order_items.quantity as dish_qty',
                DB::raw("'consumption' as type")
            )
            ->get();

        return $loads->concat($consumptions)->sortByDesc('date')->values();
    }

    /**
     * Ottiene solo i materiali con stock basso (sotto soglia)
     */
    public function getLowStockMaterials(): Collection
    {
        return $this->calculateAllStocks()->filter(fn($stock) => $stock['is_low']);
    }

    /**
     * Ottiene tutti gli importati raggruppati per materiale
     */
    protected function getAllImported(): array
    {
        return DB::table('material_stocks')
            ->select('material_id', DB::raw('SUM(stock) as total'))
            ->groupBy('material_id')
            ->pluck('total', 'material_id')
            ->map(fn($v) => (float) $v)
            ->toArray();
    }

    /**
     * Ottiene tutti i consumi raggruppati per materiale
     */
    protected function getAllConsumed(): array
    {
        return DB::table('order_items')
            ->join('dishes', 'order_items.dish_id', '=', 'dishes.id')
            ->join('dish_materials', 'dishes.id', '=', 'dish_materials.dish_id')
            ->join('table_orders', 'order_items.table_order_id', '=', 'table_orders.id')
            ->where('table_orders.status', 'paid')
            ->whereNull('order_items.deleted_at')
            ->select('dish_materials.material_id', DB::raw('SUM(order_items.quantity * dish_materials.quantity) as total'))
            ->groupBy('dish_materials.material_id')
            ->pluck('total', 'material_id')
            ->map(fn($v) => (float) $v)
            ->toArray();
    }
}
