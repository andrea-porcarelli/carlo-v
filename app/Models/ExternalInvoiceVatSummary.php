<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalInvoiceVatSummary extends Model
{
    protected $fillable = [
        'external_invoice_id',
        'vat_rate',
        'vat_nature',
        'taxable_amount',
        'tax_amount',
    ];

    public function invoice()
    {
        return $this->belongsTo(ExternalInvoice::class);
    }
}
