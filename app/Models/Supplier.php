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

}
