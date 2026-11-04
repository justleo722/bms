<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'unit',
        'buy_price',
        'sell_price',
        'supplier',
        'image',
    ];

    protected function casts(): array
    {
        return [
            'buy_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'product_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_id');
    }
}
