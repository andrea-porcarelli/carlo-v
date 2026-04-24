<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrecontoSplit extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_order_id',
        'label',
        'items',
        'covers',
        'total',
        'discount_type',
        'discount_amount',
        'discount_value',
        'status',
        'payment_method',
        'paid_at',
    ];

    protected $casts = [
        'items'           => 'array',
        'covers'          => 'integer',
        'total'           => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_value'  => 'decimal:2',
        'paid_at'         => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(TableOrder::class, 'table_order_id');
    }

    public function corrispettivi(): HasMany
    {
        return $this->hasMany(TableOrderCorrispettivo::class)->orderBy('id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
