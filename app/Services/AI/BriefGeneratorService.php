<?php

namespace App\Services\AI;

use App\Models\AiAction;
use App\Models\AiBrief;
use App\Models\AnalyticsSnapshot;
use App\Models\Brand;
use App\Models\BusinessGoal;
use App\Models\KnowledgeBase;
use App\Models\RevenueLeak;
use App\Services\AI\AiGatewayService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psy\Exception\Exception;

class BriefGeneratorService
{
    protected AiGatewayService $aiGateway;

    public function __construct(AiGatewayService $aiGateway)
    {
        $this->aiGateway =$aiGateway;
    }

    public function generateForBrand(Brand $brand): ?AiBrief
    {
        Log::info('BriefGenerator: Starting for brand', ['brand_id' => $brand->id]);

        if (!$brand->is_active) {
            Log::info('BriefGenerator: Brand is not active', ['brand_id' => $brand->id]);
            return null;
        }

        if (!$this->aiGateway->isAvailable()) {
            Log::warning('BriefGenerator: AI service not available', [
                'brand_id' => $brand->id,
                'provider' => $this->aiGateway->getProvider()
            ]);
            return null;
        }

        $hasData = AnalyticsSnapshot::where('brand_id',$brand->id)
            ->where('source', 'ga4')
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->exists();

        if (!$hasData) {
            Log::warning('BriefGenerator: No GA4 data found', ['brand_id' => $brand->id]);
            return null;
        }

        $promptData =$this->buildPromptData($brand);$fingerprint = $this->generateFingerprint($promptData);

        // Deduplication check: return existing brief if data hasn't changed today
        $existingBrief = AiBrief::where('brand_id',$brand->id)
            ->where('fingerprint', $fingerprint)
            ->where('created_at', '>=', now()->subHours(20))
            ->first();

        if ($existingBrief) {
            Log::info('BriefGenerator: Brief already exists for current dataset', [
                'brand_id'    => $brand->id,
                'fingerprint' => $fingerprint
            ]);
            return $existingBrief;
        }

        Log::info('BriefGenerator: Requesting AI generation', ['brand_id' => $brand->id]);

        $aiResponse =$this->aiGateway->generate([
            'system_prompt'   => $this->getSystemPrompt($brand),
            'user_prompt'     => json_encode($promptData, JSON_PRETTY_PRINT),
            'temperature'     => 0.7,
            'max_tokens'      => 4096,
            'response_format' => 'json',
        ]);

        if (!($aiResponse['success'] ?? false) || empty($aiResponse['content'])) {
            Log::error('BriefGenerator: AI generation failed', [
                'brand_id' => $brand->id,
                'error'    => $aiResponse['error'] ?? 'Empty or unsuccessful response',
            ]);
            return null;
        }

        $parsedData = $this->parseAiResponse($aiResponse['content']);

        if (empty($parsedData)) {
            Log::error('BriefGenerator: Failed to parse valid JSON from AI output', [
                'brand_id' => $brand->id,
                'raw_body' => $aiResponse['content'],
            ]);
            return null;
        }

        return DB::transaction(function () use ($brand, $fingerprint,$parsedData, $aiResponse) {$brief = AiBrief::create([
                'brand_id'                 => $brand->id,
                'brief_date'               => Carbon::today(),
                'fingerprint'              => $fingerprint,
                'strategic_diagnosis'      => $parsedData['strategic_diagnosis'] ?? 'No diagnosis provided.',
                'estimated_revenue_impact' => $parsedData['estimated_revenue_impact'] ?? 0.00,                 'confidence_score'         =>$parsedData['confidence_score'] ?? null,
                'raw_llm_output'           => $parsedData,
                'ai_provider'              => $this->aiGateway->getProvider(),
                'model_used'               => $aiResponse['model_used'] ?? null,
                'tokens_used'              => $aiResponse['tokens_used'] ?? 0,                 'response_time_ms'         =>$aiResponse['response_time_ms'] ?? 0,
            ]);

            if (!empty($parsedData['actions']) && is_array($parsedData['actions'])) {
                foreach ($parsedData['actions'] as$actionData) {
                    AiAction::create([
                        'brand_id'          => $brand->id,
                        'brief_id'          => $brief->id,
                        'title'             => $actionData['title'] ?? 'Untitled Action',
                        'category'          => $actionData['category'] ?? 'strategy',
                        'description'       => $actionData['description'] ?? '',
                        'suggested_content' => $actionData['suggested_content'] ?? null,
                        'content_draft'     => $actionData['content_draft'] ?? null,
                        'target_platform'   => $actionData['target_platform'] ?? null,
                        'target_url'        => $actionData['target_url'] ?? null,
                        'estimated_impact'  => $actionData['estimated_impact'] ?? null,
                        'priority'          => $actionData['priority'] ?? 1,
                        'status'            => 'pending',
                    ]);
                }
            }

            Log::info('BriefGenerator: Successfully generated brief and actions', [
                'brand_id' => $brand->id,
                'brief_id' => $brief->id,
                'actions'  => count($parsedData['actions'] ?? []),
            ]);

            return $brief;
        });
    }

    protected function buildPromptData(Brand $brand): array
    {
        $today = Carbon::today();
        $last30Days =$today->copy()->subDays(30);
        $last7Days =$today->copy()->subDays(7);

        return [
            'brand' => [
                'name'        => $brand->name,
                'domain_type' => $brand->domain_type ?? 'digital business',
                'brand_voice' => $brand->brand_voice ?? 'Professional, concise, and data-driven',
                'timezone'    => $brand->timezone ?? 'UTC',
            ],
            'analytics'      => $this->getAnalyticsSummary($brand, $last30Days,$last7Days),
            'revenue_leaks'  => RevenueLeak::where('brand_id', $brand->id)
                ->where('status', 'open')
                ->orderBy('estimated_loss', 'desc')
                ->limit(5)
                ->get()
                ->toArray(),
            'knowledge_base' => KnowledgeBase::where('brand_id', $brand->id)
                ->where('is_active', true)
                ->pluck('content', 'key')
                ->toArray(),
            'business_goals' => BusinessGoal::where('brand_id', $brand->id)
                ->where('is_active', true)
                ->get()
                ->toArray(),
            'date'           => $today->toDateString(),
        ];
    }

    protected function getAnalyticsSummary(Brand $brand, Carbon $last30Days, Carbon $last7Days): array
    {
        $metrics = [];
        $cumulativeMetrics = ['visitors', 'sessions', 'page_views', 'conversions', 'revenue'];

        foreach ($cumulativeMetrics as $metricName) {
            $data = AnalyticsSnapshot::where('brand_id', $brand->id)
            ->where('source', 'ga4')
            ->where('metric', $metricName)
            ->whereNull('dimension')
            ->where('date', '>=', $last30Days->toDateString())
            ->get();

            $metrics[$metricName] = [
                'total' => (float) $data->sum('value'),
                'average' => round((float) $data->avg('value'), 2),
                'max' => round((float) $data->max('value'), 2),
                'min' => round((float) $data->min('value'), 2),
            ];
        }

        // Get the base URL for the brand
        $baseUrl = $brand->website_url ?? 'https://' . $brand->slug . '.com';
        $baseUrl = rtrim($baseUrl, '/');

        // Top pages with FULL URLs
        $topPages = AnalyticsSnapshot::where('brand_id', $brand->id)
        ->where('source', 'ga4')
        ->where('metric', 'visitors')
        ->whereNotNull('dimension')
        ->where('dimension', 'not like', 'source_%')
        ->where('date', '>=', $last30Days->toDateString())
        ->select('dimension')
        ->selectRaw('SUM(value) as total_visitors')
        ->groupBy('dimension')
        ->orderBy('total_visitors', 'desc')
        ->limit(5)
        ->get()
        ->map(function ($item) use ($baseUrl) {
            // Ensure full URL
            $url = $item->dimension;
            if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                $url = $baseUrl . '/' . ltrim($url, '/');
                $url = preg_replace('/(?<!:)\/+/', '/', $url);
            }
            return [
                'dimension' => $url,
                'total_visitors' => $item->total_visitors,
            ];
        })
        ->toArray();

        // Channels (these are usually source_* so keep as is)
        $channels = AnalyticsSnapshot::where('brand_id', $brand->id)
        ->where('source', 'ga4')
        ->where('metric', 'visitors')
        ->where('dimension', 'like', 'source_%')
        ->where('date', '>=', $last30Days->toDateString())
        ->select('dimension')
        ->selectRaw('SUM(value) as total_visitors')
        ->groupBy('dimension')
        ->orderBy('total_visitors', 'desc')
        ->get()
        ->toArray();

        return [
            'period' => [
                'last_30_days' => $last30Days->toDateString(),
                'last_7_days' => $last7Days->toDateString(),
                'today' => Carbon::today()->toDateString(),
            ],
            'metrics' => $metrics,
            'top_pages' => $topPages,
            'channels' => $channels,
        ];
    }

    protected function getSystemPrompt(Brand $brand): string
    {
        $brandVoice =$brand->brand_voice ?? 'Professional and Data-Driven';
        $domainType =$brand->domain_type ?? 'digital business';

        return <<<PROMPT
You are the Chief Marketing Officer for {$brand->name}, a {$domainType} business.

Your objective is to analyze performance metrics, revenue leaks, and business goals to output a high-impact daily brief.

Guiding Principles:
1. Identify the single highest-value opportunity or worst revenue leak.
2. Quantify potential revenue loss or gain directly.
3. Formulate 3-5 distinct, prioritized action items.
4. Voice guidelines: "{$brandVoice}"

CRITICAL: Return ONLY a valid, raw JSON object matching the exact format below. Do not wrap output in markdown codeblocks (e.g. ```json).

Output Schema:
{
    "strategic_diagnosis": "Clear executive summary describing performance, opportunities, or leaks.",
    "estimated_revenue_impact": 1500.00,
    "confidence_score": 85,
    "actions": [
        {
            "title": "Actionable task title",
            "category": "seo|content|social|email|web_copy|campaign|strategy|analytics",
            "description": "Step-by-step guidance on execution.",
            "suggested_content": "Short text for emails or social posts.",
            "content_draft": "Complete copy draft for blogs, web pages, or newsletters.",
            "target_platform": "facebook|linkedin|twitter|blog|email",
            "target_url": "/page-path-to-update",
            "estimated_impact": 500.00,
            "priority": 1
        }
    ]
}
PROMPT;
    }

    protected function parseAiResponse(string $response): array
    {
        try {
            $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($response));
            $data = json_decode($cleaned, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('BriefGenerator: JSON decode failed', [
                    'error' => json_last_error_msg(),
                    'raw'   => $response
                ]);
                return [];
            }

            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            Log::error('BriefGenerator: Exception while parsing response', ['error' => $e->getMessage()]);
            return [];
        }
    }

    protected function generateFingerprint(array $promptData): string
    {
        unset($promptData['date']);
        if (isset($promptData['analytics']['period'])) {
            unset($promptData['analytics']['period']);
        }

        $this->recursiveKsort($promptData);
        return hash('sha256', json_encode($promptData));
    }

    private function recursiveKsort(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveKsort($value);
            }
        }
    }
}
