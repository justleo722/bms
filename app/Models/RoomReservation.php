<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomReservation extends Model
{
    protected $fillable = [
        'room_id',
        'user_id',
        'guest_name',
        'guest_phone',
        'check_in_date',
        'nights',
        'guests',
        'rate',
        'deposit',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date:Y-m-d',
            'nights' => 'integer',
            'guests' => 'integer',
            'rate' => 'decimal:2',
            'deposit' => 'decimal:2',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RoomPayment::class);
    }

    public function stays(): HasMany
    {
        return $this->hasMany(RoomStay::class);
    }
}
