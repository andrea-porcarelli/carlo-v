<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStock extends Model
{
    protected $fillable = [
        'material_id',
        'external_invoice_line_id',
        'stock',
        'purchase_date',
        'purchase_price',
        'notes',
    ];

    protected $casts = [
        'purchase_date'  => 'date',
        'purchase_price' => 'decimal:2',
        'stock'          => 'decimal:4',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function external_invoice_line(): BelongsTo
    {
        return $this->belongsTo(ExternalInvoiceLine::class);
    }
}
