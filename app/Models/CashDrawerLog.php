<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDrawerLog extends Model
{
    protected $fillable = [
        'table_order_id',
        'operation_id',
        'event_type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function tableOrder(): BelongsTo
    {
        return $this->belongsTo(TableOrder::class);
    }
}
