<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'table_order_id',
        'dish_id',
        'added_by',
        'quantity',
        'unit_price',
        'subtotal',
        'notes',
        'extras',
        'removals',
        'status',
        'segue',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'extras' => 'array',
        'removals' => 'array',
        'segue' => 'boolean',
    ];

    /**
     * Get the order for this item
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(TableOrder::class, 'table_order_id');
    }

    /**
     * Get the dish for this item
     */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /**
     * Get the user who added this item
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Calculate subtotal based on quantity, unit price and extras
     */
    public function calculateSubtotal(): float
    {
        $extrasTotal = 0;
        if ($this->extras && is_array($this->extras)) {
            $extrasTotal = array_sum($this->extras);
        }

        return ($this->unit_price + $extrasTotal) * $this->quantity;
    }

    /**
     * Update the subtotal
     */
    public function updateSubtotal(): void
    {
        $this->update(['subtotal' => $this->calculateSubtotal()]);
    }

    /**
     * Boot method to auto-calculate subtotal
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            if (!$item->subtotal || $item->isDirty(['quantity', 'unit_price', 'extras'])) {
                $item->subtotal = $item->calculateSubtotal();
            }
        });

        static::saved(function ($item) {
            // Update order total when item is saved
            $item->order->updateTotal();
        });

        static::deleted(function ($item) {
            // Update order total when item is deleted
            $item->order->updateTotal();
        });
    }
}
