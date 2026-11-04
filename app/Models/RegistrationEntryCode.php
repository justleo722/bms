<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationEntryCode extends Model
{
    protected $fillable = [
        'code_hash',
        'code_encrypted',
        'consumed_at',
        'consumed_by',
    ];

    protected function casts(): array
    {
        return [
            'consumed_at' => 'datetime',
            'code_encrypted' => 'encrypted',
        ];
    }
}
