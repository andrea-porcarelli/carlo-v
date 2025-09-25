<?php

namespace App\Repositories;

use App\Interfaces\SupplierOrderInterface;
use App\Models\SupplierOrder;
use Illuminate\Database\Eloquent\Builder;

class SupplierOrderRepository extends CrudRepository implements SupplierOrderInterface
{

    public function __construct(SupplierOrder $model)
    {
        parent::__construct($model);
    }

    public function filters(array $filters): Builder
    {
        $builder = $this->builder();
        if (isset($filters['brand_id'])) {
            $builder->where('brand_id', $filters['brand_id']);
        }
        if (isset($filters['supplier_id'])) {
            $builder->whereHas('setting', function ($q) use ($filters) {
                return $q->where('supplier_id', $filters['supplier_id']);
            });
        }
        if (isset($filters['code'])) {
            $builder->where('external_code', $filters['code']);
        }
        if (isset($filters['season_id'])) {
            $builder->where('season_id', $filters['season_id']);
        }
        return $builder;
    }

    public function types(): array
    {
        return $this->model->types();
    }
}
