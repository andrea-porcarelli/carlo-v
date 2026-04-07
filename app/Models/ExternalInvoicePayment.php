<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalInvoicePayment extends Model
{
    protected $fillable = [
        'external_invoice_id',
        'payment_method',
        'due_date',
        'amount',
        'iban',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function invoice()
    {
        return $this->belongsTo(ExternalInvoice::class);
    }
}
