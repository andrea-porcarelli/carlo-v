<?php

namespace App\Repositories;

use App\Interfaces\AllergenInterface;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Allergen;

class AllergenRepository extends CrudRepository implements AllergenInterface
{

    public function __construct(Allergen $model)
    {
        parent::__construct($model);
    }

    public function filters(array $filters): Builder
    {
        $builder = $this->builder();
        return $builder;
    }
}
