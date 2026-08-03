<?php

namespace App\Livewire;

use App\Helpers\UnitConverter;
use App\Models\Dish;
use App\Services\DishCostEstimatorService;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Stima food-cost per la ricetta corrente del piatto.
 * Riceve gli aggiornamenti dalla sibling `DishMaterialsManager` tramite l'evento
 * `dish-materials-updated` con la ricetta già normalizzata in unità base.
 */
class DishCostBreakdown extends Component
{
    public ?int $dishId = null;

    /** @var array<int, array{material_id:int, quantity_base:float}> */
    public array $items = [];

    public function mount(?int $dishId = null): void
    {
        $this->dishId = $dishId;

        // Inizializza dalla ricetta persistita (se il piatto esiste), così il
        // box mostra un valore utile prima che l'utente tocchi il form.
        if ($dishId) {
            $dish = Dish::with('materials')->find($dishId);
            if ($dish) {
                foreach ($dish->materials as $material) {
                    $this->items[] = [
                        'material_id'   => (int) $material->id,
                        'quantity_base' => (float) ($material->pivot->quantity ?? 0),
                    ];
                }
            }
        }
    }

    #[On('dish-materials-updated')]
    public function onRecipeChanged(array $items): void
    {
        $this->items = array_values(array_map(fn($it) => [
            'material_id'   => (int) ($it['material_id'] ?? 0),
            'quantity_base' => (float) ($it['quantity_base'] ?? 0),
        ], $items));
    }

    public function render()
    {
        $breakdown = app(DishCostEstimatorService::class)->getRecipeCostBreakdown($this->items);

        // Formattazione display: converti la quantità base nell'unità "leggibile"
        // (kg→g se <1, cl→ml se <1, cl→l se ≥100) per il breakdown.
        $breakdown['materials'] = array_map(function ($m) {
            $baseUnit = $m['unit'] ?: 'pz';
            $qtyBase  = (float) $m['qty_base'];
            $smart    = $this->smartUnit($qtyBase, $baseUnit);
            $m['display_qty']  = $smart['quantity'];
            $m['display_unit'] = $smart['unit'];
            return $m;
        }, $breakdown['materials']);

        // Prezzo di vendita per il food-cost % (se disponibile)
        $sellingPrice = null;
        if ($this->dishId) {
            $sellingPrice = (float) Dish::whereKey($this->dishId)->value('price');
        }

        return view('livewire.dish-cost-breakdown', [
            'breakdown'    => $breakdown,
            'sellingPrice' => $sellingPrice,
        ]);
    }

    private function smartUnit(float $baseQty, string $baseUnit): array
    {
        if ($baseUnit === 'kg') {
            if ($baseQty > 0 && $baseQty < 1) {
                return ['quantity' => round($baseQty * 1000, 4), 'unit' => 'g'];
            }
            return ['quantity' => $baseQty, 'unit' => 'kg'];
        }
        if ($baseUnit === 'cl') {
            if ($baseQty > 0 && $baseQty < 1) {
                return ['quantity' => round($baseQty * 10, 4), 'unit' => 'ml'];
            }
            if ($baseQty >= 100) {
                return ['quantity' => round($baseQty / 100, 4), 'unit' => 'l'];
            }
            return ['quantity' => $baseQty, 'unit' => 'cl'];
        }
        return ['quantity' => $baseQty, 'unit' => $baseUnit];
    }
}
