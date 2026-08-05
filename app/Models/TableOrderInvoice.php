<?php

namespace App\Models;

use App\Facades\Utils;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class TableOrderInvoice extends Model
{
    const START_SERIES = 'A0000';

    public static function toAlphanumeric(int $increment): string
    {
        $baseDec = (int) base_convert(self::START_SERIES, 36, 10);
        $newDec  = $baseDec + $increment;
        $code    = strtoupper(base_convert((string) $newDec, 10, 36));
        return str_pad(substr($code, -5), 5, '0', STR_PAD_LEFT);
    }

    protected $fillable = [
        'table_order_id',
        'customer_id',
        'invoice_code',
        'progressivo_invio',
        'invoice_name',
        'amount',
        'discount',
        'tax',
        'description',
        'lines',
        'payment_method',
        'xml_path',
        'xml_content',
        'mysond_response',
        'sdi_status',
        'sdi_status_label',
        'sdi_checked_at',
        'sdi_response',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'discount'       => 'decimal:2',
        'tax'            => 'decimal:2',
        'lines'          => 'array',
        'sent_at'        => 'datetime',
        'sdi_checked_at' => 'datetime',
        'sdi_status'     => 'integer',
    ];

    /**
     * Mapping dei codici di stato notifica SDI restituiti da MySond/ePortale (§1.3).
     * Vedi https://guide.eportale.eu/webservice/ — sezione codici notifica.
     */
    public const SDI_STATUS_LABELS = [
        -1 => 'In attesa invio SDI',
        0  => 'Presa in carico',
        1  => 'Rifiutata',
        6  => 'Scartata',
        7  => 'Consegnata',
        8  => 'Mancata consegna',
        9  => 'Accettata (PA)',
        10 => 'Rifiutata (PA)',
        11 => 'Decorrenza termini',
        12 => 'Attestazione trasmissione impossibile',
        20 => 'Notifica esito',
    ];

    public static function sdiStatusLabel(?int $code): ?string
    {
        if ($code === null) {
            return null;
        }
        return self::SDI_STATUS_LABELS[$code] ?? ('Stato ' . $code);
    }

    public const SDI_REJECTED_CODES = [1, 6, 8, 10];

    /**
     * Stati SDI terminali positivi: Consegnata / Accettata. Una volta raggiunti
     * non si torna indietro — usati per proteggere i record dal downgrade
     * automatico durante il sync MySond (es. doppio importFeAttivo dove il
     * primo è stato consegnato e il secondo scartato come duplicato).
     */
    public const SDI_TERMINAL_POSITIVE = [7, 9];

    /**
     * Una fattura è modificabile se è stata scartata da SDI o se la generazione
     * XML / invio MySond locale è andato in errore: in entrambi i casi va
     * rettificata e rinviata.
     */
    public function isEditable(): bool
    {
        if ($this->status === 'error') {
            return true;
        }
        return $this->sdi_status !== null
            && in_array($this->sdi_status, self::SDI_REJECTED_CODES, true);
    }

    public function tableOrder(): BelongsTo
    {
        return $this->belongsTo(TableOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function mysondLogs(): HasMany
    {
        return $this->hasMany(InvoiceMysondLog::class, 'table_order_invoice_id')
            ->orderByDesc('created_at');
    }

    /**
     * Compatibility accessor for InvoiceService::make_xml which accesses $invoice->user
     */
    public function getUserAttribute(): ?Customer
    {
        return $this->customer;
    }

    /**
     * Returns a single-item collection compatible with InvoiceService::make_xml.
     * The row represents one invoice line "Pasto completo" (or the given description).
     */
    public function rows(): InvoiceRowsProxy
    {
        return new InvoiceRowsProxy($this);
    }

    public function getYearAttribute(): string
    {
        return Carbon::parse($this->created_at)->format('Y');
    }

    public function getMonthAttribute(): string
    {
        return Carbon::parse($this->created_at)->format('m');
    }
}

/**
 * Proxy object that mimics an Eloquent relationship builder for InvoiceService compatibility.
 * InvoiceService calls $invoice->rows()->get() expecting a collection of row objects.
 */
class InvoiceRowsProxy
{
    public function __construct(private TableOrderInvoice $invoice) {}

    public function get(): Collection
    {
        $vatRate = (float) Utils::setting('invoice_vat_rate', 10);

        // Multi-line invoice (e.g. standalone backoffice issuance): each stored line
        // already carries label, unit_price (lordo), quantity and optional vat_rate.
        $storedLines = $this->invoice->lines;
        if (is_array($storedLines) && count($storedLines) > 0) {
            return collect($storedLines)->map(function ($line) use ($vatRate) {
                $lineVat   = isset($line['vat_rate']) ? (float) $line['vat_rate'] : $vatRate;
                $unitGross = (float) ($line['unit_price'] ?? 0);
                $unitNet   = round($unitGross / (1 + $lineVat / 100), 2);

                $tax = new \stdClass();
                $tax->tax = $lineVat;

                $row = new \stdClass();
                $row->label        = $line['label'] ?? '';
                $row->price        = $unitNet;
                $row->quantity     = (float) ($line['quantity'] ?? 1);
                $row->tax          = $tax;
                $row->tax_id       = null;
                $row->cart_product = null;
                return $row;
            });
        }

        // Single-line fallback (legacy: pasto completo from table order)
        $imponibile = round((float) $this->invoice->amount / (1 + $vatRate / 100), 2);

        $tax = new \stdClass();
        $tax->tax = $vatRate;

        $row = new \stdClass();
        $row->label        = $this->invoice->description ?: 'Pasto completo';
        $row->price        = $imponibile;
        $row->quantity     = 1;
        $row->tax          = $tax;
        $row->tax_id       = null;
        $row->cart_product = null;

        return collect([$row]);
    }
}
