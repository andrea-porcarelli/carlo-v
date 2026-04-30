<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'sort_order',
        'autoconsumo_user_id',
        'first_printed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'extras' => 'array',
        'removals' => 'array',
        'segue' => 'boolean',
        'first_printed_at' => 'datetime',
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
     * Get the material snapshot for this item
     */
    public function materials(): HasMany
    {
        return $this->hasMany(OrderItemMaterial::class);
    }

    /**
     * Get the user assigned for autoconsumo of this item
     */
    public function autoconsumoUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autoconsumo_user_id');
    }

    /**
     * Whether this item is a segue separator (no dish attached)
     */
    public function isSegueItem(): bool
    {
        return $this->segue && is_null($this->dish_id);
    }

    /**
     * Calculate subtotal based on quantity, unit price and extras
     */
    public function calculateSubtotal(): float
    {
        // Segue items have no price
        if ($this->isSegueItem()) {
            return 0.0;
        }

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

        static::creating(function ($item) {
            // Auto-assign sort_order if not explicitly set
            if (is_null($item->sort_order) || $item->sort_order === 0) {
                $max = static::where('table_order_id', $item->table_order_id)->max('sort_order') ?? 0;
                $item->sort_order = $max + 1;
            }
        });

        static::saving(function ($item) {
            // Segue items always have price 0
            if (is_null($item->dish_id) && $item->segue) {
                $item->unit_price = 0;
                $item->subtotal = 0;
                return;
            }
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
