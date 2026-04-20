<?php

namespace App\Models;

use App\Facades\Utils;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class TableOrderInvoice extends Model
{
    const START_SERIES = 'A0000';

    public static function toAlphanumeric(int $increment): string
    {
        $baseDec = (int) base_convert(self::START_SERIES, 36, 10);
        $newDec  = $baseDec + $increment;
        $code    = strtoupper(base_convert((string) $newDec, 10, 36));
        return substr($code, -5);
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
        'payment_method',
        'xml_path',
        'xml_content',
        'mysond_response',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'discount' => 'decimal:2',
        'tax'      => 'decimal:2',
        'sent_at'  => 'datetime',
    ];

    public function tableOrder(): BelongsTo
    {
        return $this->belongsTo(TableOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
        $vatRate  = (float) Utils::setting('invoice_vat_rate', 10);
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
