<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Traits\LogsActivity;

class SupplierInvoice extends LogsModel
{
    use LogsActivity;

    public const DOCUMENT_TYPE_INVOICE     = 'TD01';
    public const DOCUMENT_TYPE_CREDIT_NOTE = 'TD04';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public $fillable = [
        'supplier_id',
        'invoice_number',
        'filename',
        'document_type',
        'amount',
        'invoice_date',
        'ignored_at',
    ];

    protected $withCount = ['products'];

    protected $casts = [
        'invoice_date' => 'datetime',
        'ignored_at'   => 'datetime',
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

    public function isCreditNote(): bool
    {
        return $this->document_type === self::DOCUMENT_TYPE_CREDIT_NOTE;
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
