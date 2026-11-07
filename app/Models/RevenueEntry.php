<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueEntry extends Model
{
    protected $fillable = ['brand_id', 'month', 'source', 'amount', 'currency', 'note', 'created_by'];

    protected $casts = ['month' => 'date', 'amount' => 'decimal:2'];
}
