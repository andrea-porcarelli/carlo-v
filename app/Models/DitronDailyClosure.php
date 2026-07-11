<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DitronDailyClosure extends Model
{
    protected $table = 'ditron_daily_closures';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENDING = 'sending';
    public const STATUS_DONE    = 'done';
    public const STATUS_FAILED  = 'failed';

    public const SOURCE_AUTO   = 'auto';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'closure_date',
        'source',
        'status',
        'tipo',
        'idempotency_key',
        'receipt_number',
        'raw_command',
        'raw_err',
        'elapsed_ms',
        'agent_mode',
        'attempts',
        'last_error',
        'operator_id',
        'sent_at',
    ];

    protected $casts = [
        'closure_date'   => 'date',
        'tipo'           => 'integer',
        'receipt_number' => 'integer',
        'elapsed_ms'     => 'integer',
        'attempts'       => 'integer',
        'sent_at'        => 'datetime',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getLogContext(): array
    {
        return [
            'ditron_daily_closure_id' => $this->id,
            'closure_date'            => $this->closure_date?->toDateString(),
            'source'                  => $this->source,
            'status'                  => $this->status,
            'attempts'                => $this->attempts,
            'operator_id'             => $this->operator_id,
        ];
    }
}
