<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenRawMaterialMovement extends Model
{
    protected $fillable = [
        'raw_material_id', 'user_id', 'type', 'quantity_delta', 'unit_cost', 'reason', 'metadata',
    ];

    protected function casts(): array
    {
        return ['quantity_delta' => 'decimal:4', 'unit_cost' => 'decimal:2', 'metadata' => 'array'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(KitchenRawMaterial::class, 'raw_material_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
