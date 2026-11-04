<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCounter extends Model
{
    protected $fillable = ['name', 'slug'];

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'counter_id');
    }
}
