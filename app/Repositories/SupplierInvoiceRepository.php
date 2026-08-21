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

        if (!empty($filters['document_type']) && in_array($filters['document_type'], [
            SupplierInvoice::DOCUMENT_TYPE_INVOICE,
            SupplierInvoice::DOCUMENT_TYPE_CREDIT_NOTE,
        ], true)) {
            $builder->where('document_type', $filters['document_type']);
        }

        // 'da_effettuare' = ha almeno un prodotto senza materiale (e non ignorato)
        // 'effettuata'    = tutti i prodotti hanno materiale o sono ignorati
        if (!empty($filters['mapping'])) {
            if ($filters['mapping'] === 'da_effettuare') {
                $builder->whereHas('products', function ($q) {
                    $q->whereDoesntHave('material', function ($query) {
                        $query->join('supplier_invoices', 'supplier_invoice_products.supplier_invoice_id', '=', 'supplier_invoices.id')
                            ->whereColumn('mapping_products.supplier_id', 'supplier_invoices.supplier_id');
                    })->where('ignore_mapping', 0);
                });
            } elseif ($filters['mapping'] === 'effettuata') {
                $builder->whereDoesntHave('products', function ($q) {
                    $q->whereDoesntHave('material', function ($query) {
                        $query->join('supplier_invoices', 'supplier_invoice_products.supplier_invoice_id', '=', 'supplier_invoices.id')
                            ->whereColumn('mapping_products.supplier_id', 'supplier_invoices.supplier_id');
                    })->where('ignore_mapping', 0);
                });
            }
        }
        if (!empty($filters['import'])) {
            if ($filters['import'] === 'da_effettuare') {
                $builder->whereHas('products', function ($q) {
                    $q->whereHas('material', function ($query) {
                        $query->join('supplier_invoices', 'supplier_invoice_products.supplier_invoice_id', '=', 'supplier_invoices.id')
                            ->whereColumn('mapping_products.supplier_id', 'supplier_invoices.supplier_id');
                    })
                        ->where('ignore_mapping', 0)
                        ->whereDoesntHave('stock');
                });
            }
        }

        // Le note di credito non generano movimenti di magazzino: escludile dai flussi
        // di "da mappare" e "da importare".
        if (!empty($filters['mapping']) || !empty($filters['import'])) {
            $builder->where('document_type', SupplierInvoice::DOCUMENT_TYPE_INVOICE);
        }

        return $builder;
    }

    public function types(): array
    {
        return $this->model->types();
    }
}
