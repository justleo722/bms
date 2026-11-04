<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosOrder extends Model
{
    protected $fillable = [
        'docket_number', 'user_id', 'counter_id', 'table_id', 'payment_mode_id',
        'payment_method', 'payment_reference', 'status', 'notes', 'subtotal', 'total',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class, 'order_id');
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(InventoryCounter::class, 'counter_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(PosTable::class, 'table_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
