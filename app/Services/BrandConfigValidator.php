<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BrandConfigValidator
{
    /**
     * Validate brand configuration based on domain type.
     */
    public function validate(string $domainType, array $config): void
    {
        $domainTypes = config('brand.domain_types', []);

        if (!isset($domainTypes[$domainType])) {
            throw new \InvalidArgumentException("Invalid domain type: {$domainType}");
        }

        $requiredKeys = $domainTypes[$domainType]['required_config_keys'] ?? [];

        foreach ($requiredKeys as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException("Missing required config key: {$key}");
            }
        }

        // Validate specific domain configs
        $method = 'validate' . ucfirst($domainType) . 'Config';
        if (method_exists($this, $method)) {
            $this->$method($config);
        }

        Log::info('Brand config validated', [
            'domain_type' => $domainType,
            'config_keys' => array_keys($config),
        ]);
    }

    /**
     * Validate marketing config (GA4, Social, etc.).
     */
    protected function validateMarketingConfig(array $config): void
    {
        if (isset($config['ga4_property_id'])) {
            if (!preg_match('/^[0-9]+$/', $config['ga4_property_id'])) {
                throw new \InvalidArgumentException('GA4 Property ID must be a numeric string.');
            }
        }

        if (isset($config['ga4_measurement_id'])) {
            if (!preg_match('/^G-[A-Z0-9]+$/', $config['ga4_measurement_id'])) {
                throw new \InvalidArgumentException('GA4 Measurement ID must be in format G-XXXXXXXX.');
            }
        }

        if (isset($config['facebook_page_id']) && !empty($config['facebook_page_id'])) {
            if (!preg_match('/^[0-9]+$/', $config['facebook_page_id'])) {
                throw new \InvalidArgumentException('Facebook Page ID must be numeric.');
            }
        }

        if (isset($config['linkedin_company_id']) && !empty($config['linkedin_company_id'])) {
            if (!preg_match('/^[0-9]+$/', $config['linkedin_company_id'])) {
                throw new \InvalidArgumentException('LinkedIn Company ID must be numeric.');
            }
        }
    }

    /**
     * Validate healthcare config.
     */
    protected function validateHealthcareConfig(array $config): void
    {
        if (isset($config['fhir_endpoint'])) {
            if (!filter_var($config['fhir_endpoint'], FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('FHIR endpoint must be a valid URL.');
            }
        }
    }

    /**
     * Validate education config.
     */
    protected function validateEducationConfig(array $config): void
    {
        if (isset($config['lms_url'])) {
            if (!filter_var($config['lms_url'], FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('LMS URL must be a valid URL.');
            }
        }
    }
}