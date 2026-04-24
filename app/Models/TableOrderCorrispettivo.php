<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableOrderCorrispettivo extends Model
{
    use HasFactory;

    protected $table = 'table_order_corrispettivi';

    public const TIPO_EMISSIONE = 'emissione';
    public const TIPO_ANNULLO   = 'annullo';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_SENDING   = 'sending';
    public const STATUS_SENT      = 'sent';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'table_order_id',
        'preconto_split_id',
        'tipo',
        'annulla_corrispettivo_id',
        'progressivo_sdi',
        'identificativo_sdi',
        'payment_method',
        'importo_totale',
        'imponibile',
        'iva',
        'aliquota_iva',
        'status',
        'attempts',
        'max_attempts',
        'last_error',
        'soap_request',
        'soap_response',
        'sent_at',
        'operator_id',
    ];

    protected $casts = [
        'importo_totale' => 'decimal:2',
        'imponibile'     => 'decimal:2',
        'iva'            => 'decimal:2',
        'aliquota_iva'   => 'decimal:2',
        'attempts'       => 'integer',
        'max_attempts'   => 'integer',
        'sent_at'        => 'datetime',
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

    public function emissioneAnnullata(): BelongsTo
    {
        return $this->belongsTo(self::class, 'annulla_corrispettivo_id');
    }

    public function annulli(): HasMany
    {
        return $this->hasMany(self::class, 'annulla_corrispettivo_id');
    }

    public function isEmissione(): bool
    {
        return $this->tipo === self::TIPO_EMISSIONE;
    }

    public function isAnnullo(): bool
    {
        return $this->tipo === self::TIPO_ANNULLO;
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

    public function canCancel(): bool
    {
        return $this->isEmissione()
            && $this->status === self::STATUS_SENT
            && !empty($this->progressivo_sdi)
            && $this->annulli()->where('status', self::STATUS_SENT)->doesntExist();
    }

    public function getLogContext(): array
    {
        return [
            'corrispettivo_id'   => $this->id,
            'table_order_id'     => $this->table_order_id,
            'preconto_split_id'  => $this->preconto_split_id,
            'tipo'               => $this->tipo,
            'attempt'            => $this->attempts,
            'operator_id'        => $this->operator_id,
        ];
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'In attesa',
            self::STATUS_SENDING   => 'In invio',
            self::STATUS_SENT      => 'Inviato',
            self::STATUS_FAILED    => 'Fallito',
            self::STATUS_CANCELLED => 'Annullato',
            default                => $this->status,
        };
    }

    public function getTipoLabel(): string
    {
        return $this->isAnnullo() ? 'Annullo' : 'Emissione';
    }
}
