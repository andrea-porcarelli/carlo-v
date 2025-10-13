<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

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
        'price',
        'quantity',
        'ignore_mapping',
        'iva',
        'price',
    ];

    public function invoice() : BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function material() : HasOneThrough
    {
        return $this->hasOneThrough(
            Material::class,           // Modello finale
            MappingProduct::class,     // Modello intermedio
            'product_name', // Foreign key su mapping_products
            'id',                      // Foreign key su materials
            'product_name',                      // Local key su supplier_invoice_products
            'material_id'              // Local key su mapping_products
        );
    }

    public function stock() : BelongsTo
    {
        return $this->belongsTo(MaterialStock::class, 'id', 'supplier_invoice_product_id');
    }
}
