<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TableOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'restaurant_table_id',
        'covers',
        'status',
        'total_amount',
        'opened_at',
        'closed_at',
        'waiter_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * Get the table for this order
     */
    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class);
    }

    /**
     * Get all items in this order
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the waiter who handled this order
     */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /**
     * Calculate and update the total amount (including cover charge)
     */
    public function updateTotal(): void
    {
        $itemsTotal = $this->items()->sum('subtotal');
        $coverCharge = $this->getCoverChargeAmount();
        $total = $itemsTotal + $coverCharge;
        $this->update(['total_amount' => $total]);
    }

    /**
     * Get the items subtotal (without cover charge)
     */
    public function getItemsSubtotal(): float
    {
        return (float) $this->items()->sum('subtotal');
    }

    /**
     * Get the cover charge amount
     * Returns 0 if covers is 0 (drinks mode)
     */
    public function getCoverChargeAmount(): float
    {
        // No cover charge for drinks mode (covers = 0)
        if ($this->covers <= 0) {
            return 0.00;
        }

        $coverChargePerPerson = Setting::getCoverCharge();
        return $coverChargePerPerson * $this->covers;
    }

    /**
     * Get cover charge per person
     */
    public function getCoverChargePerPerson(): float
    {
        return Setting::getCoverCharge();
    }

    /**
     * Check if this order has cover charge
     */
    public function hasCoverCharge(): bool
    {
        return $this->covers > 0;
    }

    /**
     * Check if order is open
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Close the order
     */
    public function close(): void
    {
        $this->update([
            'status' => 'paid',
            'closed_at' => now(),
        ]);

        // Free the table
        $this->restaurantTable->update(['status' => 'free']);
    }

    public function getStatusLabel()
    {
        $statuses = [
            'open' => 'Occupato / Aperto',
            'paid' => 'Pagato',
            'cancelled' => 'Cancellato',
        ];
        return $statuses[$this->status] ?? '';
    }

    public function getStatusLevel()
    {
        $statuses = [
            'open' => 'info',
            'paid' => 'success',
            'cancelled' => 'danger',
        ];
        return $statuses[$this->status] ?? '';
    }

    public function getStatusIcon()
    {
        $statuses = [
            'open' => 'fa-time',
            'paid' => 'fa-check-circle',
            'cancelled' => 'fa-trash-alt',
        ];
        return $statuses[$this->status] ?? '';
    }
}
