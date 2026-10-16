<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentCycleRun extends Model
{
    protected $fillable = ['brand_id', 'status', 'summary', 'started_at', 'finished_at'];

    protected $casts = [
        'summary'     => 'array',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];
}
