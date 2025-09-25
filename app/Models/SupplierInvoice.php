<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class SupplierInvoice extends LogsModel
{
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public $fillable = [
        'supplier_id',
        'invoice_number',
        'filename',
        'amount',
        'invoice_date',
    ];


    public function supplier() : BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function products() : HasMany
    {
        return $this->hasMany(SupplierInvoiceProduct::class);
    }

    public function getSupplierLabelAttribute() : string {
        return $this->supplier->label;
    }

}
