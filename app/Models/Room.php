<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    public const STATUSES = ['available', 'occupied', 'reserved', 'cleaning', 'maintenance'];

    protected $fillable = [
        'number',
        'type',
        'floor',
        'rate',
        'capacity',
        'amenities',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'capacity' => 'integer',
        ];
    }

    public function currentStay(): HasOne
    {
        return $this->hasOne(RoomStay::class)->where('status', 'in_house')->latestOfMany();
    }

    public function stays(): HasMany
    {
        return $this->hasMany(RoomStay::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(RoomReservation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RoomPayment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(RoomExpense::class);
    }
}
