<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalInvoiceAttachment extends Model
{
    protected $fillable = [
        'external_invoice_id',
        'file_name',
        'mime_type',
        'base64_content',
    ];

    public function invoice()
    {
        return $this->belongsTo(ExternalInvoice::class);
    }
}
