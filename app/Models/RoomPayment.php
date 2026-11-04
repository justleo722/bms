<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomPayment extends Model
{
    protected $fillable = [
        'room_id',
        'room_stay_id',
        'room_reservation_id',
        'user_id',
        'guest_name',
        'amount',
        'payment_method',
        'paid_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date:Y-m-d',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function stay(): BelongsTo
    {
        return $this->belongsTo(RoomStay::class, 'room_stay_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(RoomReservation::class, 'room_reservation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
