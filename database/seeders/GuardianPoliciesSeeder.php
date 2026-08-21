<?php

namespace Database\Seeders;

use App\Models\GuardianPolicy;
use Illuminate\Database\Seeder;

class GuardianPoliciesSeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            // Content Filter Policies
            [
                'name' => 'Profanity Filter',
                'type' => 'content_filter',
                'description' => 'Blocks profanity and inappropriate language',
                'rules' => [
                    ['type' => 'contains', 'value' => 'offensive_word_1'],
                    ['type' => 'contains', 'value' => 'offensive_word_2'],
                ],
                'severity' => 'high',
                'is_active' => true,
            ],
            [
                'name' => 'PII Protection',
                'type' => 'content_filter',
                'description' => 'Prevents sharing of Personally Identifiable Information',
                'rules' => [
                    ['type' => 'regex', 'pattern' => '/\b\d{3}-\d{2}-\d{4}\b/'],
                    ['type' => 'regex', 'pattern' => '/\b\d{16}\b/'],
                ],
                'severity' => 'critical',
                'is_active' => true,
            ],

            // Rate Limit Policies
            [
                'name' => 'API Rate Limit',
                'type' => 'rate_limit',
                'description' => 'Limits the number of API calls per minute',
                'rules' => [
                    ['type' => 'calls_per_minute', 'value' => 60],
                ],
                'severity' => 'medium',
                'is_active' => true,
            ],

            // Safety Check Policies
            [
                'name' => 'Medical Safety Check',
                'type' => 'safety_check',
                'description' => 'Ensures medical advice is not given without disclaimers',
                'rules' => [
                    ['type' => 'contains', 'value' => 'diagnose'],
                ],
                'severity' => 'critical',
                'is_active' => true,
            ],
        ];

        foreach ($policies as $policy) {
            // Use firstOrCreate to avoid duplicates
            GuardianPolicy::firstOrCreate(
                [
                    'name' => $policy['name'],
                    'type' => $policy['type'],
                ],
                $policy
            );
        }

        $this->command->info('Guardian policies seeded successfully!');
    }
}