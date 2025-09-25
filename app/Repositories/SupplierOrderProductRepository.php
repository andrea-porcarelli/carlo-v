<?php

namespace App\Repositories;

use App\Interfaces\SupplierOrderProductInterface;
use App\Models\SupplierInvoiceProduct;
use Illuminate\Database\Eloquent\Builder;

class SupplierOrderProductRepository extends CrudRepository implements SupplierOrderProductInterface
{

    public function __construct(SupplierInvoiceProduct $model)
    {
        parent::__construct($model);
    }

    public function filters(array $filters): Builder
    {
        $builder = $this->builder();
        if (isset($filters['code'])) {
            $builder->where('manufacturer_code', $filters['code']);
        }
        if (isset($filters['manufacturer_code'])) {
            $builder->where('manufacturer_code', 'like', '%' . $filters['manufacturer_code'] . '%');
        }

        if (isset($filters['supplier_order_id'])) {
            $builder->where('supplier_order_id', $filters['supplier_order_id']);
        }

        if (isset($filters['manufacturer_color'])) {
            $builder->where('manufacturer_color', $filters['manufacturer_color']);
        }

        if (isset($filters['brand_id'])) {
            $builder->where('brand_id', $filters['brand_id']);
        }

        if (isset($filters['ean'])) {
            $builder->where('ean', $filters['ean']);
        }

        if (isset($filters['size'])) {
            $builder->where('size', $filters['size']);
        }
        return $builder;
    }
}
