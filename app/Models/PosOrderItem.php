<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosOrderItem extends Model
{
    protected $fillable = [
        'product_type', 'product_id', 'product_name', 'quantity', 'unit_price', 'line_total',
    ];
}
