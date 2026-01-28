<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStock extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public $fillable = [
        'material_id',
        'supplier_invoice_product_id',
        'stock',
        'purchase_date',
        'purchase_price',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
    ];

    public function material() : BelongsTo {
        return $this->belongsTo(Material::class);
    }
    public function supplier_invoice_product() : BelongsTo {
        return $this->belongsTo(SupplierInvoiceProduct::class);
    }

}
