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
     * Uno scontrino di vendita è annullabile se emesso con successo, non già annullato,
     * e con fiscal_number nel formato ZZZZNNNN (blob concatenato Z + progressivo giorno).
     * Da quel blob + created_at ricostruiamo i 4 identificativi del DOCANNULLO: non
     * dipendiamo dai campi fiscal_date/z_number salvati dall'agent, inaffidabili per
     * bug di parsing della property 12 GETP.
     */
    public function isCancellable(): bool
    {
        return $this->isSale()
            && $this->isSent()
            && !$this->isCancelled()
            && ctype_digit((string) $this->fiscal_number)
            && strlen((string) $this->fiscal_number) >= 5
            && filled($this->matricola);
    }

    /**
     * Ricostruisce i 4 identificativi necessari al DOCANNULLO (NUMSCO/DATASCO/ZNUMBER/
     * MATRICOLA) partendo dai dati salvati con certezza al momento dell'emissione.
     *
     * fiscal_number arriva dalla cassa come blob "ZZZZNNNN": ultime 4 cifre = progressivo
     * scontrino nella giornata (NUMSCO), tutto ciò che precede = numero chiusura Z (ZNUMBER).
     * fiscal_date lo prendiamo da created_at perché la property 12 restituisce una stringa
     * in formato italiano che viene salvata invertita.
     *
     * @return array{fiscal_number: string, fiscal_date: string, z_number: int, matricola: string}
     */
    public function buildCancelPayload(): array
    {
        $blob = (string) $this->fiscal_number;

        return [
            'fiscal_number' => (string) ((int) substr($blob, -4)),
            'fiscal_date'   => $this->created_at->toDateString(),
            'z_number'      => (int) substr($blob, 0, -4),
            'matricola'     => (string) $this->matricola,
        ];
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
