<?php

namespace App\Repositories;

use App\Interfaces\SupplierInvoiceInterface;
use App\Models\SupplierInvoice;
use App\Repositories\CrudRepository;
use Illuminate\Database\Eloquent\Builder;

class SupplierInvoiceRepository extends CrudRepository implements SupplierInvoiceInterface
{

    public function __construct(SupplierInvoice $model)
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
        if (isset($filters['invoice_code'])) {
            $builder->where('external_code', $filters['code']);
        }
        return $builder;
    }

    public function types(): array
    {
        return $this->model->types();
    }
}
