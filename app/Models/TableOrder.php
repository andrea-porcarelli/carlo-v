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
        'autoconsumo',
        'payment_method',
        'opened_at',
        'closed_at',
        'waiter_id',
        'cash_drawer_operation_id',
        'preconto_requested_at',
        'discount_type',
        'discount_amount',
        'discount_value',
    ];

    protected $casts = [
        'total_amount'    => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_value'  => 'decimal:2',
        'opened_at'       => 'datetime',
        'closed_at'       => 'datetime',
        'preconto_requested_at' => 'datetime',
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
        return $this->hasMany(OrderItem::class)->orderBy('sort_order');
    }

    /**
     * Get the waiter who handled this order
     */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    /**
     * Get preconto splits for this order
     */
    public function precontoSplits(): HasMany
    {
        return $this->hasMany(PrecontoSplit::class);
    }

    public function tableOrderInvoices(): HasMany
    {
        return $this->hasMany(TableOrderInvoice::class);
    }

    /**
     * Corrispettivi elettronici (emissioni e annulli) associati al tavolo
     */
    public function corrispettivi(): HasMany
    {
        return $this->hasMany(TableOrderCorrispettivo::class)->orderBy('id');
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
     * Returns true if a discount has been applied to this order
     */
    public function hasDiscount(): bool
    {
        return $this->discount_type !== null && (float) $this->discount_value > 0;
    }

    /**
     * Total after discount (= total_amount - discount_value)
     */
    public function getDiscountedTotal(): float
    {
        return max(0, (float) $this->total_amount - (float) ($this->discount_value ?? 0));
    }

    /**
     * Apply or clear a discount on this order (persists to DB)
     */
    public function applyDiscount(string $type, float $amount): void
    {
        $rawTotal = (float) $this->total_amount;
        $discountValue = match ($type) {
            'percent' => round($rawTotal * min($amount, 100) / 100, 2),
            'value'   => min($amount, $rawTotal),
            default   => 0.0,
        };
        $this->update([
            'discount_type'   => $type,
            'discount_amount' => $amount,
            'discount_value'  => $discountValue,
        ]);
    }

    /**
     * Remove any applied discount
     */
    public function clearDiscount(): void
    {
        $this->update([
            'discount_type'   => null,
            'discount_amount' => null,
            'discount_value'  => null,
        ]);
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
    public function close(string $paymentMethod = 'pos'): void
    {
        $this->update([
            'status' => 'paid',
            'closed_at' => now(),
            'payment_method' => $paymentMethod,
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
