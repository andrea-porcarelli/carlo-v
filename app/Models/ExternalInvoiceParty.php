<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalInvoiceParty extends Model
{
    protected $fillable = [
        'external_invoice_id',
        'type',
        'company_name',
        'first_name',
        'last_name',
        'vat_number',
        'tax_code',
        'address',
        'street_number',
        'zip_code',
        'city',
        'province',
        'country',
    ];

    public function invoice()
    {
        return $this->belongsTo(ExternalInvoice::class);
    }
}
