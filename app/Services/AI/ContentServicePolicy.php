<?php

namespace App\Services\AI;

use App\Models\Brand;
use App\Models\ContentDraft;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ContentServicePolicy
{
    /**
     * Daily and hourly limits per brand.
     */
    protected array $limits = [
        'per_hour'  => 10,   // max 10 content generations per hour per brand
        'per_day'   => 50,   // max 50 per day per brand
        'min_words' => 300,  // minimum generated word count
        'max_words' => 5000, // maximum generated word count
    ];

    /**
     * Blocked topics (add as needed).
     */
    protected array $blockedTopics = [
        'politics', 'religion', 'gambling', 'adult', 'weapons',
    ];

    /**
     * Check if a content generation request is allowed.
     * Returns [allowed => bool, reason => string|null]
     */
    public function canGenerate(Brand $brand, string $topic, string $template): array
    {
        // 1. Rate limit – hourly
        $hourlyCount = ContentDraft::where('brand_id', $brand->id)
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->count();

        if ($hourlyCount >= $this->limits['per_hour']) {
            Log::warning('Content rate limit hit (hourly)', [
                'brand_id' => $brand->id,
                'count' => $hourlyCount,
            ]);
            return [
                'allowed' => false,
                'reason' => "Hourly limit reached ({$this->limits['per_hour']}/hr).",
            ];
        }

        // 2. Rate limit – daily
        $dailyCount = ContentDraft::where('brand_id', $brand->id)
            ->whereDate('created_at', Carbon::today())
            ->count();

        if ($dailyCount >= $this->limits['per_day']) {
            Log::warning('Content rate limit hit (daily)', [
                'brand_id' => $brand->id,
                'count' => $dailyCount,
            ]);
            return [
                'allowed' => false,
                'reason' => "Daily limit reached ({$this->limits['per_day']}/day).",
            ];
        }

        // 3. Blocked topics
        $lowerTopic = strtolower($topic);
        foreach ($this->blockedTopics as $blocked) {
            if (str_contains($lowerTopic, $blocked)) {
                Log::warning('Content generation blocked by policy', [
                    'brand_id' => $brand->id,
                    'topic' => $topic,
                    'blocked' => $blocked,
                ]);
                return [
                    'allowed' => false,
                    'reason' => "Topic contains blocked keyword: {$blocked}",
                ];
            }
        }

        // 4. Brand must be active
        if (!$brand->is_active) {
            return [
                'allowed' => false,
                'reason' => 'Brand is not active.',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Validate the AI response before saving.
     */
    public function validateOutput(string $content, string $template): array
    {
        $wordCount = str_word_count(strip_tags($content));

        if ($wordCount < $this->limits['min_words']) {
            return [
                'valid' => false,
                'reason' => "Generated content too short ({$wordCount} words, min {$this->limits['min_words']}).",
            ];
        }

        if ($wordCount > $this->limits['max_words']) {
            return [
                'valid' => false,
                'reason' => "Generated content too long ({$wordCount} words, max {$this->limits['max_words']}).",
            ];
        }

        // Block obvious prompt injection output
        $forbidden = ['IGNORE PREVIOUS', 'SYSTEM:', 'ASSISTANT:'];
        foreach ($forbidden as $phrase) {
            if (stripos($content, $phrase) !== false) {
                return [
                    'valid' => false,
                    'reason' => 'Generated content contains forbidden phrases.',
                ];
            }
        }

        return ['valid' => true, 'reason' => null];
    }
}