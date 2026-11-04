<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenRecipeIngredient extends Model
{
    protected $fillable = ['recipe_id', 'raw_material_id', 'quantity', 'unit', 'waste_percent'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'waste_percent' => 'decimal:2'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(KitchenRawMaterial::class, 'raw_material_id')->withTrashed();
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(KitchenRecipe::class, 'recipe_id')->withTrashed();
    }
}
