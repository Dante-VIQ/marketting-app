<?php

namespace Database\Seeders;

use App\Models\DomainRoutingRule;
use Illuminate\Database\Seeder;

class DomainRoutingRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // Marketing Domain
            [
                'domain_type' => 'marketing',
                'intent_type' => 'analyze',
                'prompt_template_key' => 'marketing_analysis',
                'default_model' => 'llama3.1',
                'config' => [
                    'temperature' => 0.7,
                    'max_tokens' => 4096,
                ],
            ],
            [
                'domain_type' => 'marketing',
                'intent_type' => 'generate',
                'prompt_template_key' => 'marketing_content',
                'default_model' => 'llama3.1',
                'config' => [
                    'temperature' => 0.8,
                    'max_tokens' => 8192,
                ],
            ],
            [
                'domain_type' => 'marketing',
                'intent_type' => 'recommend',
                'prompt_template_key' => 'marketing_recommendations',
                'default_model' => 'llama3.1',
                'config' => [
                    'temperature' => 0.7,
                    'max_tokens' => 4096,
                ],
            ],

            // Healthcare Domain
            [
                'domain_type' => 'healthcare',
                'intent_type' => 'analyze',
                'prompt_template_key' => 'healthcare_analysis',
                'default_model' => 'llama3.1',
                'config' => [
                    'temperature' => 0.3,
                    'max_tokens' => 4096,
                ],
            ],
            [
                'domain_type' => 'healthcare',
                'intent_type' => 'diagnose',
                'prompt_template_key' => 'healthcare_diagnosis',
                'default_model' => 'llama3.1',
                'config' => [
                    'temperature' => 0.2,
                    'max_tokens' => 8192,
                ],
            ],

            // Education Domain
            [
                'domain_type' => 'education',
                'intent_type' => 'analyze',
                'prompt_template_key' => 'education_analysis',
                'default_model' => 'llama3.1',
                'config' => [
                    'temperature' => 0.5,
                    'max_tokens' => 4096,
                ],
            ],
            [
                'domain_type' => 'education',
                'intent_type' => 'recommend',
                'prompt_template_key' => 'education_recommendations',
                'default_model' => 'llama3.1',
                'config' => [
                    'temperature' => 0.6,
                    'max_tokens' => 4096,
                ],
            ],

            // Youth Development Domain
            [
                'domain_type' => 'youth',
                'intent_type' => 'analyze',
                'prompt_template_key' => 'youth_analysis',
                'default_model' => 'llama3.1',
                'config' => [
                    'temperature' => 0.6,
                    'max_tokens' => 4096,
                ],
            ],
            [
                'domain_type' => 'youth',
                'intent_type' => 'recommend',
                'prompt_template_key' => 'youth_recommendations',
                'default_model' => 'llama3.1',
                'config' => [
                    'temperature' => 0.7,
                    'max_tokens' => 4096,
                ],
            ],

            // General (Fallback)
            [
                'domain_type' => 'general',
                'intent_type' => 'analyze',
                'prompt_template_key' => 'general_analysis',
                'default_model' => 'llama3.1',
                'config' => [
                    'temperature' => 0.7,
                    'max_tokens' => 4096,
                ],
            ],
            [
                'domain_type' => 'general',
                'intent_type' => 'generate',
                'prompt_template_key' => 'general_content',
                'default_model' => 'llama3.1',
                'config' => [
                    'temperature' => 0.8,
                    'max_tokens' => 8192,
                ],
            ],
            [
                'domain_type' => 'general',
                'intent_type' => 'recommend',
                'prompt_template_key' => 'general_recommendations',
                'default_model' => 'llama3.1',
                'config' => [
                    'temperature' => 0.7,
                    'max_tokens' => 4096,
                ],
            ],
        ];

        foreach ($rules as $rule) {
            // Use updateOrCreate to avoid duplicates
            DomainRoutingRule::updateOrCreate(
                [
                    'domain_type' => $rule['domain_type'],
                    'intent_type' => $rule['intent_type'],
                ],
                $rule
            );
        }

        $this->command->info('Domain routing rules seeded successfully!');
    }
}