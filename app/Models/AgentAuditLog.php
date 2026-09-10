<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentAuditLog extends Model
{
    protected $fillable = [
        'ip_address', 'method', 'path', 'user_agent', 'brand_id', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}