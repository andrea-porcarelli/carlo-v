<?php

namespace App\Livewire;

use App\Helpers\UnitConverter;
use App\Models\Material;
use Livewire\Component;

class DishMaterialsManager extends Component
{
    public $dishId = null;
    public $materials = [];
    public $search = '';
    public $searchResults = [];
    public $selectedMaterials = [];

    protected $rules = [
        'selectedMaterials.*.quantity' => 'required|numeric|min:0.01',
    ];

    public function mount($dishId = null, $existingMaterials = [])
    {
        $this->dishId = $dishId;

        if (!empty($existingMaterials)) {
            foreach ($existingMaterials as $material) {
                // Il pivot salva sempre in unità base — convertiamo nella unità più leggibile
                $baseQty  = (float) ($material->pivot->quantity ?? 0);
                $baseUnit = $material->stock_type;
                $smart    = $this->smartUnit($baseQty, $baseUnit);

                $this->selectedMaterials[$material->id] = [
                    'id'         => $material->id,
                    'label'      => $material->label,
                    'stock_type' => $baseUnit,
                    'unit_type'  => $smart['unit'],
                    'quantity'   => $smart['quantity'],
                ];
            }
        }
    }

    /**
     * Snapshot della ricetta corrente in unità base, consumato dal componente
     * DishCostBreakdown per aggiornare la stima costo in tempo reale.
     * @return array<int, array{material_id:int, quantity_base:float}>
     */
    protected function currentRecipeSnapshot(): array
    {
        $out = [];
        foreach ($this->selectedMaterials as $m) {
            $qty       = (float) ($m['quantity'] ?? 0);
            $unitType  = $m['unit_type'] ?? $m['stock_type'];
            $baseQty   = $qty > 0 ? UnitConverter::toBase($qty, $unitType) : 0.0;
            $out[] = [
                'material_id'   => (int) $m['id'],
                'quantity_base' => $baseQty,
            ];
        }
        return $out;
    }

    protected function dispatchRecipeUpdated(): void
    {
        $this->dispatch('dish-materials-updated', items: $this->currentRecipeSnapshot());
    }

    /**
     * Converte un valore in unità base nella rappresentazione più leggibile.
     * Es. 0.025 kg → ['quantity' => 25, 'unit' => 'g']
     *     1.5 kg  → ['quantity' => 1.5, 'unit' => 'kg']
     *     75 cl   → ['quantity' => 75,  'unit' => 'cl']
     *     0.5 cl  → ['quantity' => 5,   'unit' => 'ml']
     */
    public function smartUnit(float $baseQty, string $baseUnit): array
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

    public function updatedSearch()
    {
        if (strlen($this->search) >= 2) {
            $this->searchResults = Material::where('label', 'like', '%' . $this->search . '%')
                ->whereNotIn('id', array_keys($this->selectedMaterials))
                ->limit(10)
                ->get()
                ->toArray();
        } else {
            $this->searchResults = [];
        }
    }

    public function addMaterial($materialId)
    {
        $material = Material::find($materialId);

        if ($material && !isset($this->selectedMaterials[$materialId])) {
            $this->selectedMaterials[$materialId] = [
                'id'         => $material->id,
                'label'      => $material->label,
                'stock_type' => $material->stock_type,
                'unit_type'  => $material->stock_type,
                'quantity'   => 0,
            ];

            $this->search = '';
            $this->searchResults = [];
            $this->dispatchRecipeUpdated();
        }
    }

    public function removeMaterial($materialId)
    {
        unset($this->selectedMaterials[$materialId]);
        $this->dispatchRecipeUpdated();
    }

    public function getStockTypeLabel($stockType)
    {
        $types = Material::stock_types();
        return $types[$stockType] ?? $stockType;
    }

    public function getAvailableUnits(string $stockType): array
    {
        return UnitConverter::compatibleUnits($stockType);
    }

    public function getBaseQuantity(float $qty, string $unitType): float
    {
        return UnitConverter::toBase($qty, $unitType);
    }

    public function updatedSelectedMaterials()
    {
        // Forza il re-render del componente per aggiornare il campo hidden
        $this->dispatchRecipeUpdated();
    }

    public function getMaterialsData()
    {
        $this->validate();
        return array_values($this->selectedMaterials);
    }

    public function getMaterialsJson()
    {
        $data = array_map(function ($m) {
            return [
                'id'        => $m['id'],
                'quantity'  => UnitConverter::toBase((float) ($m['quantity'] ?? 0), $m['unit_type'] ?? $m['stock_type']),
                'unit_type' => $m['stock_type'], // salva sempre l'unità base del materiale
            ];
        }, array_values($this->selectedMaterials));

        return json_encode($data);
    }

    public function render()
    {
        return view('livewire.dish-materials-manager');
    }
}