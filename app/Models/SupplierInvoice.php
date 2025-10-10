<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
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

    protected $withCount = ['products'];

    protected $casts = [
        'invoice_date' => 'datetime',
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


    public function riepilogo_iva() : Collection
    {
        return $this->products()
            ->get()
            ->groupBy('iva')
            ->map(function ($items) {
                $imponibile = $items->sum(fn($row) => $row->price * $row->quantity);
                $imposta = $items->sum(fn($row) => $row->price * $row->quantity) * ($items->first()->iva / 100);
                return [
                    'imponibile' => $imponibile,
                    'imposta' => $imposta,
                    'totale' => $imposta + $imponibile,
                ];
            });
    }
}
