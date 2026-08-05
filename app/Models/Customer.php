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

    /**
     * SDI richiede la sigla provincia in maiuscolo (2 lettere ISTAT).
     * Normalizziamo alla scrittura così l'XML FatturaPA non viene mai scartato
     * per <Provincia>xx</Provincia>.
     */
    public function setProvinceAttribute($value): void
    {
        $this->attributes['province'] = $value !== null && $value !== ''
            ? strtoupper(trim((string) $value))
            : null;
    }

    /**
     * Codice destinatario SDI: convenzione uppercase per gli alfanumerici.
     */
    public function setCodiceDestinatarioAttribute($value): void
    {
        $this->attributes['codice_destinatario'] = $value !== null && $value !== ''
            ? strtoupper(trim((string) $value))
            : null;
    }
}
