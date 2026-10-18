<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListeningQuery extends Model
{
    protected $fillable = ['brand_id', 'term', 'kind', 'is_active', 'last_checked_at', 'source_status'];

    protected $casts = ['is_active' => 'boolean', 'last_checked_at' => 'datetime', 'source_status' => 'array'];

    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class);
    }
}
