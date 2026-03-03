<?php

namespace App\Repositories;

use App\Interfaces\DishInterface;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Dish;

class DishRepository extends CrudRepository implements DishInterface
{

    public function __construct(Dish $model)
    {
        parent::__construct($model);
    }

    public function filters(array $filters): Builder
    {
        $builder = $this->builder();

        if (!empty($filters['dish'])) {
            $builder->where('label', 'like', '%' . $filters['dish'] . '%');
        }

        if (!empty($filters['category_id'])) {
            $builder->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['material_id'])) {
            $builder->whereHas('materials', function ($query) use ($filters) {
                $query->where('materials.id', $filters['material_id']);
            });
        }

        if (!empty($filters['allergen_id'])) {
            $builder->whereHas('allergens', function ($query) use ($filters) {
                $query->where('allergens.id', $filters['allergen_id']);
            });
        }

        return $builder;
    }
}
