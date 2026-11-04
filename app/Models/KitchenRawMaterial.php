<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KitchenRawMaterial extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'sku', 'unit', 'quantity', 'alert_level',
        'cost_per_unit', 'average_cost', 'last_cost', 'supplier', 'batch_no',
        'expiry_date', 'active',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'alert_level' => 'decimal:4',
            'cost_per_unit' => 'decimal:2',
            'average_cost' => 'decimal:2',
            'last_cost' => 'decimal:2',
            'expiry_date' => 'date:Y-m-d',
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KitchenRawMaterialCategory::class, 'category_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(KitchenRawMaterialMovement::class, 'raw_material_id');
    }

    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(KitchenRecipeIngredient::class, 'raw_material_id');
    }
}
