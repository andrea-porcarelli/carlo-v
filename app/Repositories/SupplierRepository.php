<?php

namespace App\Repositories;

use App\Interfaces\SupplierInterface;
use App\Models\Supplier;
use App\Repositories\CrudRepository;
use Illuminate\Database\Eloquent\Builder;

class SupplierRepository extends CrudRepository implements SupplierInterface
{

    public function __construct(Supplier $model)
    {
        parent::__construct($model);
    }

    public function filters(array $filters = []): Builder
    {
        $builder = $this->builder();

        return $builder;
    }
}
