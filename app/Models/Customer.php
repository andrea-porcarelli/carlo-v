<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'user_type',
        'full_name',
        'fiscal_code',
        'vat_number',
        'address',
        'zip_code',
        'city',
        'province',
        'codice_destinatario',
        'pec_destinatario',
    ];

    public function tableOrderInvoices(): HasMany
    {
        return $this->hasMany(TableOrderInvoice::class);
    }
}
