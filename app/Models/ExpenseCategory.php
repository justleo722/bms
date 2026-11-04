<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug'];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category', 'slug');
    }
}
