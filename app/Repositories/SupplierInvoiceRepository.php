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

        // Filtro ignorate: mostra solo le ignorate; di default le esclude sempre
        if (!empty($filters['ignored']) && $filters['ignored'] === 'ignorate') {
            $builder->whereNotNull('ignored_at');
        } else {
            $builder->whereNull('ignored_at');
        }

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
                    $q->whereDoesntHave('material', function ($query) {
                        $query->whereColumn('mapping_products.quantity_multiplier', 'supplier_invoice_products.quantity_multiplier')
                            ->join('supplier_invoices', 'supplier_invoice_products.supplier_invoice_id', '=', 'supplier_invoices.id')
                            ->whereColumn('mapping_products.supplier_id', 'supplier_invoices.supplier_id');
                    })->where('ignore_mapping', 0);
                });
            } elseif ($filters['mapping'] === 'effettuata') {
                $builder->whereDoesntHave('products', function ($q) {
                    $q->whereDoesntHave('material', function ($query) {
                        $query->whereColumn('mapping_products.quantity_multiplier', 'supplier_invoice_products.quantity_multiplier')
                            ->join('supplier_invoices', 'supplier_invoice_products.supplier_invoice_id', '=', 'supplier_invoices.id')
                            ->whereColumn('mapping_products.supplier_id', 'supplier_invoices.supplier_id');
                    })->where('ignore_mapping', 0);
                });
            }
        }
        if (!empty($filters['import'])) {
            if ($filters['import'] === 'da_effettuare') {
                $builder->whereHas('products', function ($q) {
                    $q->whereHas('material', function ($query) {
                        $query->whereColumn('mapping_products.quantity_multiplier', 'supplier_invoice_products.quantity_multiplier')
                            ->join('supplier_invoices', 'supplier_invoice_products.supplier_invoice_id', '=', 'supplier_invoices.id')
                            ->whereColumn('mapping_products.supplier_id', 'supplier_invoices.supplier_id');
                    })
                        ->where('ignore_mapping', 0)
                        ->whereDoesntHave('stock');
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
