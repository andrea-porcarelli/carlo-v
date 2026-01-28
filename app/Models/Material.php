<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public $fillable = [
        'label',
        'stock',
        'stock_type',
        'alert_threshold',
    ];

    public function stocks() : HasMany {
        return $this->hasMany(MaterialStock::class);
    }

    public function dishes() : BelongsToMany {
        return $this->belongsToMany(
            Dish::class,
            'dish_materials',
            'material_id',
            'dish_id'
        )->withPivot('quantity');
    }

    public static function stock_types() : array
    {
        return [
            'pz' => 'Pezzo',
            'g' => 'Grammi (g)',
            'ml' => 'Millilitri (ml)',
        ];
    }

    public function getStockTypeLabelAttribute() : string {
        return $this->stock_types()[$this->stock_type];
    }

    public function isLowStock(float $currentStock): bool
    {
        return $this->alert_threshold !== null && $currentStock <= $this->alert_threshold;
    }
}
