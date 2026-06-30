<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Vedi migration 2026_06_30_001_create_mirrored_invoices_table.
 *
 * Proiezione locale di una fattura MySond. Non è autorevole — MySond lo è —
 * ma serve per:
 *  - mostrare la lista completa in /backoffice/accounting senza polling SOAP
 *    ad ogni refresh pagina
 *  - tracciare gli ack delle scartate (in modo che il blocco emissione possa
 *    essere risolto dall'admin)
 *  - dare un punto di ancoraggio per l'XML scaricato lazy.
 */
class MirroredInvoice extends Model
{
    public const STATO_RIFIUTATA    = 1;
    public const STATO_SCARTATA     = 6;
    public const STATO_RIFIUTATA_PA = 10;

    public const REJECTED_CODES = [
        self::STATO_RIFIUTATA,
        self::STATO_SCARTATA,
        self::STATO_RIFIUTATA_PA,
    ];

    protected $fillable = [
        'file_name',
        'mysond_code',
        'mysond_date',
        'mysond_total',
        'customer_name',
        'customer_vat',
        'customer_cf',
        'stato',
        'stato_label',
        'xml_content',
        'xml_fetched_at',
        'local_invoice_id',
        'acknowledged_at',
        'acknowledged_by',
        'acknowledged_note',
        'first_synced_at',
        'last_synced_at',
    ];

    protected $casts = [
        'mysond_date'     => 'date',
        'mysond_total'    => 'decimal:2',
        'stato'           => 'integer',
        'xml_fetched_at'  => 'datetime',
        'acknowledged_at' => 'datetime',
        'first_synced_at' => 'datetime',
        'last_synced_at'  => 'datetime',
    ];

    public function isRejected(): bool
    {
        return $this->stato !== null
            && in_array($this->stato, self::REJECTED_CODES, true);
    }

    public function isPendingAck(): bool
    {
        return $this->isRejected() && $this->acknowledged_at === null;
    }

    public function scopePendingAck($query)
    {
        return $query->whereIn('stato', self::REJECTED_CODES)
            ->whereNull('acknowledged_at');
    }
}
