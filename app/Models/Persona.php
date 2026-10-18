<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Persona extends Model
{
    public const LIST_FIELDS = ['goals', 'triggers', 'pains', 'objections', 'motivations', 'channels'];

    protected $fillable = ['brand_id', 'name', 'role_title', 'summary', 'goals', 'triggers', 'pains', 'objections',
        'motivations', 'channels', 'buying_role', 'quote', 'is_active'];

    protected $casts = [
        'goals' => 'array', 'triggers' => 'array', 'pains' => 'array', 'objections' => 'array',
        'motivations' => 'array', 'channels' => 'array', 'is_active' => 'boolean',
    ];

    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }
    public function evidence(): HasMany { return $this->hasMany(PersonaEvidence::class); }
}
