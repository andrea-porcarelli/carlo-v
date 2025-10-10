<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Printer extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public $fillable = [
        'label',
        'ip',
        'is_active',
    ];

    public function categories() : HasMany
    {
        return $this->hasMany(Category::class);
    }
}
