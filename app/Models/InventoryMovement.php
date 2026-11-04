<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'product_id',
        'counter_id',
        'from_counter_id',
        'to_counter_id',
        'user_id',
        'type',
        'quantity_delta',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'metadata' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(InventoryCounter::class, 'counter_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
