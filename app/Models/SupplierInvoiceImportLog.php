<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceImportLog extends Model
{
    protected $fillable = [
        'supplier_invoice_id',
        'supplier_invoice_product_id',
        'material_id',
        'status',
        'product_name',
        'material_name',
        'qty_invoice',
        'quantity_multiplier',
        'stock_added',
        'stock_unit',
        'notes',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoiceProduct::class, 'supplier_invoice_product_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
