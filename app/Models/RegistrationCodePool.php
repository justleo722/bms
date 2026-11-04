<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationCodePool extends Model
{
    protected $fillable = [
        'initial_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'initial_notified_at' => 'datetime',
        ];
    }
}
