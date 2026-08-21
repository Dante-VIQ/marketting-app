<?php

namespace App\Services\AI;

use App\Models\AiAction;
use App\Models\AnalyticsSnapshot;
use App\Models\Brand;
use App\Models\KeywordRanking;
use App\Models\SeoIssue;
use App\Services\AI\AiGatewayService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SeoAssistantService
{
    protected AiGatewayService $aiGateway;

    public function __construct(AiGatewayService $aiGateway)
    {
        $this->aiGateway = $aiGateway;
    }

    /**
     * Perform daily SEO checks for a brand.
     */
    public function performDailyChecks(Brand $brand): void
    {
        DB::transaction(function () use ($brand) {
            // Check for SEO issues
            $this->checkForIssues($brand);

            // Analyze keyword rankings
            $this->analyzeKeywords($brand);

            // Generate recommendations/actions
            $this->generateRecommendations($brand);
        });
    }

    /**
     * Check for SEO issues on high-traffic pages.
     */
    protected function checkForIssues(Brand $brand): void
    {
        // Fetch top 20 pages from analytics over the last 30 days
        $pages = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('source', 'ga4')
            ->where('metric', 'visitors')
            ->whereNotNull('dimension')
            ->where('date', '>=', Carbon::today()->subDays(30)->toDateString())
            ->select('dimension', DB::raw('SUM(value) as total_visitors'))
            ->groupBy('dimension')
            ->orderByDesc('total_visitors')
            ->limit(20)
            ->get();

        foreach ($pages as $page) {
            $url = $page->dimension;

            // Add a small delay to avoid rate limiting
            usleep(100000); // 0.1 seconds

            $this->checkBrokenLinks($brand, $url);
            $this->checkMissingMeta($brand, $url);
            $this->checkContentLength($brand, $url);
            $this->checkDuplicateContent($brand, $url);
        }
    }

    /**
     * Check for broken links.
     */
    protected function checkBrokenLinks(Brand $brand, string $url): void
    {
        try {
            $response = Http::timeout(5)->get($url);
            $isBroken = $response->failed() || $response->status() >= 400;
            $statusCode = $response->status();
        } catch (\Exception $e) {
            $isBroken = true;
            $statusCode = 0;
        }

        if ($isBroken) {
            SeoIssue::firstOrCreate(
                [
                    'brand_id' => $brand->id,
                    'page_url' => $url,
                    'type'     => 'broken_link',
                    'status'   => 'open',
                ],
                [
                    'severity'       => $statusCode >= 500 ? 'critical' : 'medium',
                    'description'    => "Broken link detected on page {$url} (Status: {$statusCode})",
                    'recommendation' => "Check the URL and update or remove the broken link.",
                ]
            );
        }
    }

    /**
     * Check for missing meta tags.
     */
    protected function checkMissingMeta(Brand $brand, string $url): void
    {
        try {
            $response = Http::timeout(5)->get($url);
            $html = $response->body();

            // Check for title tag
            $hasTitle = preg_match('/<title.*?<\/title>/i', $html);
            // Check for meta description
            $hasMeta = preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/i', $html);

            $hasIssue = !$hasTitle || !$hasMeta;
        } catch (\Exception $e) {
            $hasIssue = true;
        }

        if ($hasIssue) {
            SeoIssue::firstOrCreate(
                [
                    'brand_id' => $brand->id,
                    'page_url' => $url,
                    'type'     => 'missing_meta',
                    'status'   => 'open',
                ],
                [
                    'severity'       => 'high',
                    'description'    => "Meta title or description is missing on {$url}",
                    'recommendation' => "Add an optimized title (50-60 chars) and meta description (150-160 chars).",
                ]
            );
        }
    }

    /**
     * Check for content length issues.
     */
    protected function checkContentLength(Brand $brand, string $url): void
    {
        $wordCount = 0;
        try {
            $response = Http::timeout(5)->get($url);
            $html = $response->body();

            // Remove tags to get plain text
            $text = strip_tags($html);
            $wordCount = str_word_count($text);

            $hasIssue = $wordCount < 300;
        } catch (\Exception $e) {
            $hasIssue = false;
        }

        if ($hasIssue) {
            SeoIssue::firstOrCreate(
                [
                    'brand_id' => $brand->id,
                    'page_url' => $url,
                    'type'     => 'thin_content',
                    'status'   => 'open',
                ],
                [
                    'severity'       => 'low',
                    'description'    => "Page content is under 300 words on {$url} (current: {$wordCount} words)",
                    'recommendation' => "Expand content body with relevant FAQs and detailed insights to improve SEO value.",
                ]
            );
        }
    }

    /**
     * Check for duplicate content issues.
     */
    protected function checkDuplicateContent(Brand $brand, string $url): void
    {
        try {
            $response = Http::timeout(5)->get($url);
            $html = $response->body();

            // Check for canonical tag
            $hasCanonical = preg_match('/<link\s+rel=["\']canonical["\']\s+href=["\'](.*?)["\']/i', $html);

            if (!$hasCanonical) {
                SeoIssue::firstOrCreate(
                    [
                        'brand_id' => $brand->id,
                        'page_url' => $url,
                        'type'     => 'duplicate_content',
                        'status'   => 'open',
                    ],
                    [
                        'severity'       => 'medium',
                        'description'    => "Page {$url} is missing a canonical tag",
                        'recommendation' => "Add a canonical tag to prevent duplicate content issues.",
                    ]
                );
            }
        } catch (\Exception $e) {
            // Skip on error
        }
    }

    /**
     * Analyze keyword rankings.
     */
    protected function analyzeKeywords(Brand $brand): void
    {
        // Stubbed keywords - replace with Google Search Console / SerpAPI response
        $trackedKeywords = [
            ['keyword' => 'safari kenya', 'position' => rand(1, 20)],
            ['keyword' => 'maasai mara', 'position' => rand(1, 15)],
            ['keyword' => 'kenya travel', 'position' => rand(5, 25)],
            ['keyword' => 'african safari', 'position' => rand(10, 30)],
        ];

        $today = Carbon::today()->toDateString();

        foreach ($trackedKeywords as $data) {
            $alreadyTracked = KeywordRanking::where('brand_id', $brand->id)
                ->where('keyword', $data['keyword'])
                ->whereDate('tracked_date', $today)
                ->exists();

            if ($alreadyTracked) {
                continue;
            }

            $previousRecord = KeywordRanking::where('brand_id', $brand->id)
                ->where('keyword', $data['keyword'])
                ->orderByDesc('tracked_date')
                ->first();

            KeywordRanking::create([
                'brand_id'          => $brand->id,
                'keyword'           => $data['keyword'],
                'page_url'          => '/',
                'position'          => $data['position'],
                'previous_position' => $previousRecord?->position,
                'search_volume'     => rand(100, 1000),
                'difficulty'        => ['easy', 'medium', 'hard'][rand(0, 2)],
                'tracked_date'      => $today,
            ]);
        }
    }

protected function generateRecommendations(Brand $brand): void
{
    // Get or create a "SEO Brief" for the day
    $brief = \App\Models\AiBrief::firstOrCreate(
        [
            'brand_id' => $brand->id,
            'brief_date' => Carbon::today()->toDateString(),
            'fingerprint' => 'seo_' . $brand->id . '_' . Carbon::today()->toDateString(),
        ],
        [
            'strategic_diagnosis' => 'Daily SEO recommendations',
            'estimated_revenue_impact' => 0,
            'confidence_score' => 100,
            'raw_llm_output' => [],
            'ai_provider' => 'system',
            'model_used' => 'seo_checker',
            'tokens_used' => 0,
            'response_time_ms' => 0,
        ]
    );

    $issues = SeoIssue::where('brand_id', $brand->id)
        ->where('status', 'open')
        ->whereIn('severity', ['high', 'critical'])
        ->limit(5)
        ->get();

    foreach ($issues as $issue) {
        $existingAction = AiAction::where('brand_id', $brand->id)
            ->where('brief_id', $brief->id)
            ->where('category', 'seo')
            ->where('target_url', $issue->page_url)
            ->where('title', "Fix: " . $issue->type)
            ->whereIn('status', ['pending', 'approved', 'in_progress'])
            ->exists();

        if (!$existingAction) {
            AiAction::create([
                'brand_id'          => $brand->id,
                'brief_id'          => $brief->id, // Now we have a brief_id
                'title'             => "Fix: " . $issue->type,
                'category'          => 'seo',
                'description'       => $issue->description,
                'suggested_content' => $issue->recommendation,
                'target_url'        => $issue->page_url,
                'estimated_impact'  => $issue->severity === 'critical' ? 1000 : 500,
                'priority'          => $issue->severity === 'critical' ? 5 : 3,
                'status'            => 'pending',
            ]);
        }
    }
}

    /**
     * Get open SEO issues formatted for API/UI response.
     */
    public function getRecommendations(Brand $brand): array
    {
        return SeoIssue::where('brand_id', $brand->id)
            ->where('status', 'open')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->get()
            ->map(fn ($issue) => [
                'id'             => $issue->id,
                'page'           => $issue->page_url,
                'severity'       => $issue->severity,
                'issue'          => ucfirst(str_replace('_', ' ', $issue->type)),
                'description'    => $issue->description,
                'recommendation' => $issue->recommendation,
                'status'         => $issue->status,
            ])
            ->toArray();
    }

    /**
     * Generate a human-readable SEO report.
     */
    public function generateSeoReport(Brand $brand): string
    {
        $issues = $this->getRecommendations($brand);
        $keywords = KeywordRanking::where('brand_id', $brand->id)
            ->whereDate('tracked_date', Carbon::today())
            ->orderBy('position')
            ->limit(10)
            ->get();

        $report = "## SEO Report for {$brand->name}\n\n";

        $report .= "### Top Keywords\n";
        if ($keywords->isEmpty()) {
            $report .= "No keyword rankings recorded for today.\n";
        } else {
            foreach ($keywords as $keyword) {
                $change = '';
                if ($keyword->previous_position !== null) {
                    $diff = $keyword->previous_position - $keyword->position;
                    $change = $diff > 0 ? " (↑{$diff})" : ($diff < 0 ? " (↓" . abs($diff) . ")" : " (→)");
                }
                $report .= "- **{$keyword->keyword}**: Position {$keyword->position}{$change}\n";
            }
        }

        $report .= "\n### Open Issues\n";
        if (empty($issues)) {
            $report .= "No open issues found! 🎉\n";
        } else {
            foreach ($issues as $issue) {
                $report .= "- **[" . strtoupper($issue['severity']) . "]** {$issue['issue']} on `{$issue['page']}`\n";
                $report .= "  - *Action:* {$issue['recommendation']}\n";
            }
        }

        return $report;
    }
}