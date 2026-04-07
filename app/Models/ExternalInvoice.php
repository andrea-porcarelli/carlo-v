<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalInvoice extends Model
{
    protected $fillable = [
        'sdi_identifier',
        'file_name',
        'transmission_format',
        'document_type',
        'currency',
        'number',
        'date',
        'total_amount',
        'rounding',
        'status',
        'import_error',
        'supplier_name',
        'file_path',
        'original_xml',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function transmission(): HasMany
    {
        return $this->hasMany(ExternalInvoiceTransmission::class);
    }

    public function parties(): HasMany
    {
        return $this->hasMany(ExternalInvoiceParty::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ExternalInvoiceLine::class);
    }

    public function vatSummaries(): HasMany
    {
        return $this->hasMany(ExternalInvoiceVatSummary::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ExternalInvoicePayment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExternalInvoiceAttachment::class);
    }
}
