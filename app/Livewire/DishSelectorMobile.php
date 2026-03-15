<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Dish;
use Livewire\Component;

class DishSelectorMobile extends Component
{
    public $categories;
    public $selectedCategory = null;
    public $search = '';
    public $dishes = [];

    public function mount()
    {
        $this->categories = Category::where('is_active', 1)
            ->orderBy('sort_order')
            ->get();
    }

    public function selectCategory($categoryId)
    {
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

    public function render()
    {
        return view('livewire.dish-selector-mobile');
    }
}
