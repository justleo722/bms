<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenRawMaterialCategory extends Model
{
    protected $fillable = ['name', 'slug'];

    public function materials(): HasMany
    {
        return $this->hasMany(KitchenRawMaterial::class, 'category_id');
    }
}
