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
        return $builder;
    }
}
