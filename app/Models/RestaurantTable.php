<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestaurantTable extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'table_number',
        'capacity',
        'position_x',
        'position_y',
        'status',
        'is_active',
    ];

    protected $casts = [
        'position_x' => 'decimal:2',
        'position_y' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get all orders for this table
     */
    public function orders(): HasMany
    {
        return $this->hasMany(TableOrder::class);
    }

    /**
     * Get the current active order for this table
     */
    public function activeOrder(): HasOne
    {
        return $this->hasOne(TableOrder::class)
            ->where('status', 'open')
            ->latest();
    }

    /**
     * Check if table is occupied
     */
    public function isOccupied(): bool
    {
        return $this->status === 'occupied';
    }

    /**
     * Check if table has an active order
     */
    public function hasActiveOrder(): bool
    {
        return $this->activeOrder()->exists();
    }

    /**
     * Get current order total
     */
    public function getCurrentTotal(): float
    {
        $activeOrder = $this->activeOrder;
        return $activeOrder ? $activeOrder->total_amount : 0;
    }
}
