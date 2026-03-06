<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuOption extends Model
{
    protected $fillable = ['type', 'label', 'price', 'is_active', 'sort_order'];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeExtras($q)
    {
        return $q->where('type', 'extra');
    }

    public function scopeRemovals($q)
    {
        return $q->where('type', 'removal');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
