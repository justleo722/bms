<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KitchenFoodItem extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'category', 'price', 'image', 'instructions', 'expiry_date', 'available'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'expiry_date' => 'date:Y-m-d', 'available' => 'boolean'];
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(KitchenRecipe::class, 'kitchen_food_item_recipes', 'food_item_id', 'recipe_id')
            ->whereNull('kitchen_recipes.deleted_at');
    }
}
