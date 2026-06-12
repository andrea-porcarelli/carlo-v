<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DitronReceipt extends Model
{
    use HasFactory;

    protected $table = 'ditron_receipts';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT    = 'sent';
    public const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'table_order_id',
        'preconto_split_id',
        'idempotency_key',
        'receipt_number',
        'payment_method',
        'importo_totale',
        'status',
        'attempts',
        'max_attempts',
        'last_error',
        'request_payload',
        'raw_command',
        'raw_err',
        'elapsed_ms',
        'agent_url',
        'sent_at',
        'operator_id',
    ];

    protected $casts = [
        'importo_totale'  => 'decimal:2',
        'attempts'        => 'integer',
        'max_attempts'    => 'integer',
        'receipt_number'  => 'integer',
        'elapsed_ms'      => 'integer',
        'request_payload' => 'array',
        'sent_at'         => 'datetime',
    ];

    public function tableOrder(): BelongsTo
    {
        return $this->belongsTo(TableOrder::class);
    }

    public function precontoSplit(): BelongsTo
    {
        return $this->belongsTo(PrecontoSplit::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function canRetry(): bool
    {
        return $this->status === self::STATUS_FAILED && $this->attempts < $this->max_attempts;
    }

    public function getLogContext(): array
    {
        return [
            'ditron_receipt_id' => $this->id,
            'table_order_id'    => $this->table_order_id,
            'preconto_split_id' => $this->preconto_split_id,
            'receipt_number'    => $this->receipt_number,
            'attempt'           => $this->attempts,
            'operator_id'       => $this->operator_id,
        ];
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'In attesa',
            self::STATUS_SENDING => 'In invio',
            self::STATUS_SENT    => 'Emesso',
            self::STATUS_FAILED  => 'Fallito',
            default              => $this->status,
        };
    }
}
