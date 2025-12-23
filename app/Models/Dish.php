<?php

namespace App\Models;

use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Dish extends Model
{
    use HasMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public $fillable = [
        'category_id',
        'is_active',
        'label',
        'price',
        'description',
    ];

    public function category() : BelongsTo {
        return $this->belongsTo(Category::class);
    }

    public function allergens() : BelongsToMany {
        return $this->belongsToMany(
            Allergen::class,
            'dish_allergens',
            'allergen_id',
            'dish_id'
        )->withTimestamps();
    }

    public function materials() : BelongsToMany {
        return $this->belongsToMany(
            Material::class,
            'dish_materials',
            'dish_id',
            'material_id'
        )->withPivot('quantity')->withTimestamps();
    }

    public function getIngredientsViewAttribute() : string
    {
        return view('backoffice.dishes.ingredients', ['dish' => $this])->render();
    }
}
