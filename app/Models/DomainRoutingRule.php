<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainRoutingRule extends Model
{
    protected $fillable = [
        'domain_type',
        'intent_type',
        'prompt_template_key',
        'default_model',
        'config',
        'is_active',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to filter by domain type.
     */
    public function scopeDomain($query, string $domainType)
    {
        return $query->where('domain_type', $domainType);
    }

    /**
     * Scope to filter by intent type.
     */
    public function scopeIntent($query, string $intentType)
    {
        return $query->where('intent_type', $intentType);
    }

    /**
     * Scope to get active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the full prompt template path.
     */
    public function getPromptTemplatePathAttribute(): string
    {
        return "prompts/{$this->domain_type}/{$this->prompt_template_key}.txt";
    }
}