<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public $fillable = [
        'printer_id',
        'is_active',
        'label',
    ];

    public function printer() : BelongsTo {
        return $this->belongsTo(Printer::class);
    }

    public function dishes() : HasMany {
        return $this->hasMany(Dish::class);
    }
}
