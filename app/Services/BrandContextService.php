<?php

namespace App\Services;

use App\Models\Brand;

class BrandContextService
{
    /**
     * Get the full brand context for AI prompts.
     */
    public function getFullContext(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'domain_type' => $brand->domain_type,
            'brand_voice' => $brand->brand_voice,
            'timezone' => $brand->timezone,
            'config' => $brand->config,
            'settings' => $brand->settings,
            'domain_label' => $this->getDomainLabel($brand->domain_type),
            'domain_icon' => $this->getDomainIcon($brand->domain_type),
        ];
    }

    /**
     * Get just the brand voice for prompt injection.
     */
    public function getBrandVoice(Brand $brand): string
    {
        return $brand->brand_voice;
    }

    /**
     * Get the brand's configuration.
     */
    public function getConfig(Brand $brand): array
    {
        return $brand->config;
    }

    /**
     * Get the domain label.
     */
    public function getDomainLabel(string $domainType): string
    {
        return config("brand.domain_types.{$domainType}.label", ucfirst($domainType));
    }

    /**
     * Get the domain icon.
     */
    public function getDomainIcon(string $domainType): string
    {
        return config("brand.domain_types.{$domainType}.icon", '📋');
    }
}