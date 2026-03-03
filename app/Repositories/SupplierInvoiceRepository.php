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

        if (!empty($filters['invoice_number'])) {
            $builder->where('invoice_number', 'like', '%' . $filters['invoice_number'] . '%');
        }

        if (!empty($filters['supplier_id'])) {
            $builder->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['date_from'])) {
            $builder->whereDate('invoice_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $builder->whereDate('invoice_date', '<=', $filters['date_to']);
        }

        // 'da_effettuare' = ha almeno un prodotto senza materiale (e non ignorato)
        // 'effettuata'    = tutti i prodotti hanno materiale o sono ignorati
        if (!empty($filters['mapping'])) {
            if ($filters['mapping'] === 'da_effettuare') {
                $builder->whereHas('products', function ($q) {
                    $q->whereDoesntHave('material')->where('ignore_mapping', 0);
                });
            } elseif ($filters['mapping'] === 'effettuata') {
                $builder->whereDoesntHave('products', function ($q) {
                    $q->whereDoesntHave('material')->where('ignore_mapping', 0);
                });
            }
        }

        return $builder;
    }

    public function types(): array
    {
        return $this->model->types();
    }
}
