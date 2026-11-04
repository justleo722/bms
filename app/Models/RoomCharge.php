<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomCharge extends Model
{
    protected $fillable = [
        'room_stay_id',
        'user_id',
        'description',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function stay(): BelongsTo
    {
        return $this->belongsTo(RoomStay::class, 'room_stay_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
