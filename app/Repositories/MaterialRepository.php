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

        if (!empty($filters['mixed'])) {
            $q = $filters['mixed'];
            $builder->where('label', 'like', "%{$q}%");
        }

        if (!empty($filters['stock_type'])) {
            $builder->where('stock_type', $filters['stock_type']);
        }

        return $builder;
    }
}
