<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class SupplierInvoiceProduct extends LogsModel
{
    protected int $ttl = 60 * 60 * 24 * 30;

    public $fillable = [
        'supplier_invoice_id',
        'product_name',
        'price',
        'quantity',
        'quantity_multiplier',
        'ignore_mapping',
        'iva',
    ];

    public function invoice() : BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    public function material() : HasOneThrough
    {
        return $this->hasOneThrough(
            Material::class,
            MappingProduct::class,
            'product_name',
            'id',
            'product_name',
            'material_id'
        );
    }
}
