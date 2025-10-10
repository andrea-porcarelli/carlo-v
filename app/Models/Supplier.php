<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public $fillable = [
        'company_name',
        'fiscal_code',
        'vat_number',
        'phone',
        'email',
        'sdi',
        'pec',
        'address',
        'number',
        'zip_code',
        'city',
        'province',
        'nation',
    ];

    public function invoices(): HasMany {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function getExtendedNameAttribute(): string
    {
        return $this->company_name . '<br /><small>' . $this->vat_number . ' | ' . $this->fiscal_code. ' <br />' . $this->full_address . '</small>';
    }

    public function getFullAddressAttribute(): string
    {
        return $this->address . ' ' . $this->number . ', ' . $this->zip_code . ' ' . $this->city . ' (' . $this->province .  ')';
    }

}
