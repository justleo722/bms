<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'expense_number',
        'user_id',
        'counter_id',
        'category',
        'description',
        'amount',
        'payment_method',
        'expense_date',
        'status',
        'is_recurring',
        'repeat_interval',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date:Y-m-d',
            'is_recurring' => 'boolean',
        ];
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(InventoryCounter::class, 'counter_id');
    }
}
