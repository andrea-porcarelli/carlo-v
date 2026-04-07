<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class ExternalInvoiceLine extends Model
{
    protected $fillable = [
        'external_invoice_id',
        'line_number',
        'description',
        'quantity',
        'unit_of_measure',
        'unit_price',
        'total_price',
        'vat_rate',
        'vat_nature',
        'ignore_mapping',
        'quantity_multiplier',
    ];

    protected $casts = [
        'ignore_mapping' => 'boolean',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ExternalInvoice::class);
    }

    /**
     * Materiale associato tramite MappingProduct, usando description come chiave.
     * MappingProduct.product_name = ExternalInvoiceLine.description
     */
    public function material(): HasOneThrough
    {
        return $this->hasOneThrough(
            Material::class,
            MappingProduct::class,
            'product_name',   // FK su mapping_products che punta alla description della linea
            'id',             // FK su materials
            'description',    // local key su external_invoice_lines
            'material_id'     // local key su mapping_products
        );
    }

    public function stock(): HasOne
    {
        return $this->hasOne(MaterialStock::class);
    }
}
