<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalInvoiceTransmission extends Model
{
    protected $fillable = [
        'external_invoice_id',
        'country_id',
        'vat_id',
        'progressive_number',
        'recipient_code',
        'recipient_pec',
    ];

    public function invoice()
    {
        return $this->belongsTo(ExternalInvoice::class);
    }
}
