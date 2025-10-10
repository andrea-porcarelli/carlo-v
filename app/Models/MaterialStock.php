<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStock extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public $fillable = [
        'material_id',
        'stock',
    ];

    public function material() : BelongsTo {
        return $this->belongsTo(Material::class);
    }

}
