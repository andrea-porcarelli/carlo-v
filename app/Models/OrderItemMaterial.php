<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemMaterial extends Model
{
    protected $fillable = [
        'order_item_id',
        'material_id',
        'quantity',
        'unit_type',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Normalizza quantità e unità di misura alle unità base (kg, cl, pz).
     * g → kg (÷1000), ml → cl (÷10), kg/cl/pz invariati.
     *
     * @return array{quantity: float, unit_type: string}
     */
    public static function normalizeToBaseUnit(float $quantity, ?string $unitType): array
    {
        return match ($unitType) {
            'g'  => ['quantity' => $quantity / 1000, 'unit_type' => 'kg'],
            'ml' => ['quantity' => $quantity / 10,   'unit_type' => 'cl'],
            default => ['quantity' => $quantity, 'unit_type' => $unitType],
        };
    }
}
