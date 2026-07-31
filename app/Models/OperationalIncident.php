<?php

namespace App\Models;

use App\Support\OperationalErrorCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Incidente operativo registrato dal sistema (stampa fallita, cassa non
 * raggiungibile, scontrino Ditron in errore, ...).
 *
 * È l'unica fonte di verità per il feed di notifiche visto dall'operatore
 * in frontoffice e per la dashboard di monitoraggio in backoffice.
 */
class OperationalIncident extends Model
{
    protected $table = 'operational_incidents';

    public const SEVERITY_INFO     = 'info';
    public const SEVERITY_WARN     = 'warn';
    public const SEVERITY_ERROR    = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'code',
        'severity',
        'category',
        'operator_message',
        'technical_detail',
        'context',
        'table_order_id',
        'user_id',
        'source',
        'telegram_notified_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
    ];

    protected $casts = [
        'context'              => 'array',
        'telegram_notified_at' => 'datetime',
        'acknowledged_at'      => 'datetime',
        'resolved_at'          => 'datetime',
    ];

    public function tableOrder(): BelongsTo
    {
        return $this->belongsTo(TableOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function scopeUnacknowledged(Builder $query): Builder
    {
        return $query->whereNull('acknowledged_at');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function errorCode(): ?OperationalErrorCode
    {
        return OperationalErrorCode::tryFrom($this->code);
    }

    public function severityLabel(): string
    {
        return match ($this->severity) {
            self::SEVERITY_INFO     => 'Info',
            self::SEVERITY_WARN     => 'Avviso',
            self::SEVERITY_ERROR    => 'Errore',
            self::SEVERITY_CRITICAL => 'Critico',
            default                 => ucfirst($this->severity),
        };
    }
}
