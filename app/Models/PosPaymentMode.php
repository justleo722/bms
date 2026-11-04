<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosPaymentMode extends Model
{
    protected $fillable = ['name', 'enabled', 'requires_reference', 'reference_label', 'icon'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'requires_reference' => 'boolean',
        ];
    }
}
