<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    public const TYPE_SALE   = 'sale';
    public const TYPE_CANCEL = 'cancel';

    protected $fillable = [
        'table_order_id',
        'preconto_split_id',
        'idempotency_key',
        'receipt_number',
        'fiscal_number',
        'fiscal_date',
        'z_number',
        'matricola',
        'type',
        'cancels_receipt_id',
        'cancelled_by_receipt_id',
        'cancelled_at',
        'cancel_reason',
        'cancelled_by_user_id',
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
        'z_number'        => 'integer',
        'elapsed_ms'      => 'integer',
        'request_payload' => 'array',
        'sent_at'         => 'datetime',
        'fiscal_date'     => 'date',
        'cancelled_at'    => 'datetime',
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

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /** Su record `cancel`: la sale che sta annullando. */
    public function cancelsReceipt(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cancels_receipt_id');
    }

    /** Su record `sale`: il record cancel che l'ha annullata (se presente). */
    public function cancelledByReceipt(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cancelled_by_receipt_id');
    }

    public function scopeSales(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SALE);
    }

    public function scopeCancels(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CANCEL);
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isSale(): bool
    {
        return $this->type === self::TYPE_SALE;
    }

    public function isCancel(): bool
    {
        return $this->type === self::TYPE_CANCEL;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Uno scontrino di vendita è annullabile solo se emesso con successo,
     * ha i dati fiscali per costruire il DOCANNULLO, e non è già stato annullato.
     */
    public function isCancellable(): bool
    {
        return $this->isSale()
            && $this->isSent()
            && !$this->isCancelled()
            && filled($this->fiscal_number)
            && filled($this->fiscal_date)
            && filled($this->z_number)
            && filled($this->matricola);
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
            'fiscal_number'     => $this->fiscal_number,
            'z_number'          => $this->z_number,
            'type'              => $this->type,
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

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_SALE   => 'Vendita',
            self::TYPE_CANCEL => 'Annullo',
            default           => $this->type,
        };
    }
}
