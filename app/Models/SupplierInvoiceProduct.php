<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceProduct extends LogsModel
{
    protected int $ttl = 60 * 60 * 24 * 30;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public $fillable = [
        'supplier_invoice_id',
        'product_name',
        'quantity',
        'iva',
        'price',
    ];

    public function invoice() : BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function product() : BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
