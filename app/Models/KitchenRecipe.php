<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KitchenRecipe extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'code', 'category', 'type', 'output_quantity', 'output_unit',
        'prep_time', 'cook_time', 'version', 'instructions', 'active',
    ];

    protected function casts(): array
    {
        return ['output_quantity' => 'decimal:4', 'active' => 'boolean'];
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(KitchenRecipeIngredient::class, 'recipe_id');
    }

    public function foodItems(): BelongsToMany
    {
        return $this->belongsToMany(KitchenFoodItem::class, 'kitchen_food_item_recipes', 'recipe_id', 'food_item_id');
    }
}
