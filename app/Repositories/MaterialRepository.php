<?php

namespace App\Repositories;

use App\Interfaces\MaterialInterface;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Material;

class MaterialRepository extends CrudRepository implements MaterialInterface
{

    public function __construct(Material $model)
    {
        parent::__construct($model);
    }

    public function filters(array $filters): Builder
    {
        $builder = $this->builder();
        return $builder;
    }
}
