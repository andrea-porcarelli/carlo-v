<?php

namespace App\Livewire;

use App\Models\Allergen;
use Livewire\Component;

class DishAllergensManager extends Component
{
    public $dishId = null;
    public $availableAllergens = [];
    public $selectedAllergens = [];

    public function mount($dishId = null, $existingAllergens = [])
    {
        $this->dishId = $dishId;

        // Carica tutti gli allergeni disponibili
        $this->availableAllergens = Allergen::orderBy('label')->get()->toArray();

        // Imposta gli allergeni già selezionati se in modifica
        if (!empty($existingAllergens)) {
            $this->selectedAllergens = $existingAllergens->pluck('id')->toArray();
        }
    }

    public function toggleAllergen($allergenId)
    {
        if (in_array($allergenId, $this->selectedAllergens)) {
            // Rimuovi se già selezionato
            $this->selectedAllergens = array_diff($this->selectedAllergens, [$allergenId]);
        } else {
            // Aggiungi se non selezionato
            $this->selectedAllergens[] = $allergenId;
        }
    }

    public function isSelected($allergenId)
    {
        return in_array($allergenId, $this->selectedAllergens);
    }

    public function getAllergensData()
    {
        return $this->selectedAllergens;
    }

    public function render()
    {
        return view('livewire.dish-allergens-manager');
    }
}
