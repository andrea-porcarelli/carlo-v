<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    public function stock(): HasOne
    {
        return $this->hasOne(MaterialStock::class, 'supplier_invoice_product_id');
    }

    public function invoice() : BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    /**
     * Restituisce il materiale associato tramite mapping_products.
     * Nota: questa relazione non filtra per supplier_id, quindi in caso di omonimi
     * tra fornitori diversi può restituire il materiale errato. Usare solo per display.
     * Per creazione di stock, fare lookup diretto su MappingProduct con supplier_id.
     */
    public function material() : HasOneThrough
    {
        return $this->hasOneThrough(
            Material::class,
            MappingProduct::class,
            'product_name',
            'id',
            'product_name',
            'material_id'
        )
            ->where('mapping_products.quantity_multiplier', $this->quantity_multiplier)
            ->where('mapping_products.supplier_id', $this->invoice->supplier_id);
    }
}
