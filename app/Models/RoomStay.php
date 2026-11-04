<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomStay extends Model
{
    protected $fillable = [
        'room_id',
        'room_reservation_id',
        'user_id',
        'guest_name',
        'guest_phone',
        'guests',
        'rate',
        'check_in_at',
        'expected_checkout_at',
        'checked_out_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'guests' => 'integer',
            'rate' => 'decimal:2',
            'check_in_at' => 'datetime',
            'expected_checkout_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(RoomReservation::class, 'room_reservation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(RoomCharge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RoomPayment::class);
    }
}
