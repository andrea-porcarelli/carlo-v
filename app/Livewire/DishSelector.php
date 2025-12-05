<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Dish;
use Livewire\Component;

class DishSelector extends Component
{
    public $categories;
    public $selectedCategory = null;
    public $search = '';
    public $dishes = [];

    public function mount()
    {
        // Carica solo le categorie attive
        $this->categories = Category::where('is_active', 1)
            ->orderBy('label')
            ->get();
    }

    public function selectCategory($categoryId)
    {
        // Se la categoria è già selezionata, la deseleziono
        if ($this->selectedCategory == $categoryId) {
            $this->selectedCategory = null;
            $this->dishes = [];
        } else {
            $this->selectedCategory = $categoryId;
            $this->loadDishes();
        }

        $this->search = '';
    }

    public function updatedSearch()
    {
        $this->loadDishes();
    }

    private function loadDishes()
    {
        if (!$this->selectedCategory) {
            $this->dishes = [];
            return;
        }

        $query = Dish::where('category_id', $this->selectedCategory)
            ->where('is_active', 1);

        if ($this->search) {
            $query->where('label', 'like', '%' . $this->search . '%');
        }

        $this->dishes = $query->orderBy('label')->get();
    }

    public function selectDish($dishId)
    {
        $dish = Dish::find($dishId);

        if ($dish) {
            // Emette un evento che può essere catturato dal parent component o JavaScript
            $this->dispatch('dishSelected', [
                'id' => $dish->id,
                'name' => $dish->label,
                'price' => $dish->price,
            ]);
        }
    }

    public function render()
    {
        return view('livewire.dish-selector');
    }
}
