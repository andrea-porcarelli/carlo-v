<?php

namespace App\Livewire;

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

        // Carica gli ingredienti esistenti se in modifica
        if (!empty($existingMaterials)) {
            foreach ($existingMaterials as $material) {
                $this->selectedMaterials[$material->id] = [
                    'id' => $material->id,
                    'label' => $material->label,
                    'stock_type' => $material->stock_type,
                    'quantity' => $material->pivot->quantity ?? 0,
                ];
            }
        }
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
                'id' => $material->id,
                'label' => $material->label,
                'stock_type' => $material->stock_type,
                'quantity' => 0,
            ];

            $this->search = '';
            $this->searchResults = [];
        }
    }

    public function removeMaterial($materialId)
    {
        unset($this->selectedMaterials[$materialId]);
    }

    public function getStockTypeLabel($stockType)
    {
        $types = Material::stock_types();
        return $types[$stockType] ?? $stockType;
    }

    public function updatedSelectedMaterials()
    {
        // Questo metodo viene chiamato automaticamente quando selectedMaterials cambia
        // Forza il re-render del componente per aggiornare il campo hidden
    }

    public function getMaterialsData()
    {
        // Valida i dati prima di restituirli
        $this->validate();

        return array_values($this->selectedMaterials);
    }

    public function getMaterialsJson()
    {
        return json_encode(array_values($this->selectedMaterials));
    }

    public function render()
    {
        return view('livewire.dish-materials-manager');
    }
}
