<?php

namespace App\Agents;

use Strands\Agents\Agent;
use Strands\Agents\Tools\HttpTool;
use Strands\Agents\Tools\FileTool;
use Strands\Agents\Tools\DatabaseTool;
use App\Models\Brand;
use App\Models\AnalyticsSnapshot;
use App\Models\AiBrief;
use App\Models\AiAction;
use App\Services\Analytics\GA4Service;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MarketingAgent extends Agent
{
    protected GA4Service $ga4Service;
    protected $brandId;

    public function __construct()
    {
        parent::__construct([
            'name' => 'Vumbi Marketing Agent',
            'description' => 'Autonomous marketing agent for small businesses that handles content creation, SEO monitoring, lead management, and campaign tracking.',
            'version' => '1.0.0',
            'tools' => [
                new HttpTool(),
                new FileTool(),
                new DatabaseTool(),
            ],
        ]);

        $this->ga4Service = app(GA4Service::class);
    }

    /**
     * Run the daily marketing workflow for a brand.
     */
    public function runDailyWorkflow(int $brandId): array
    {
        $this->brandId = $brandId;
        $brand = Brand::find($brandId);

        if (!$brand) {
            return ['error' => 'Brand not found'];
        }

        Log::info('MarketingAgent: Starting daily workflow', ['brand_id' => $brandId]);

        try {
            // Step 1: Collect analytics
            $analytics = $this->collectAnalytics($brand);
            
            // Step 2: Analyze data
            $analysis = $this->analyzeData($analytics, $brand);
            
            // Step 3: Generate brief
            $brief = $this->generateBrief($analysis, $brand);
            
            // Step 4: Create actions
            $actions = $this->createActions($brief, $brand);
            
            // Step 5: Generate content for approved actions
            $content = $this->generateContent($actions, $brand);

            return [
                'success' => true,
                'brand' => $brand->name,
                'analytics' => $analytics,
                'brief' => $brief,
                'actions' => $actions,
                'content' => $content,
                'timestamp' => Carbon::now()->toDateTimeString(),
            ];
        } catch (\Exception $e) {
            Log::error('MarketingAgent: Workflow failed', [
                'brand_id' => $brandId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'brand_id' => $brandId,
            ];
        }
    }

    /**
     * Collect analytics from GA4.
     */
    protected function collectAnalytics(Brand $brand): array
    {
        Log::info('MarketingAgent: Collecting analytics', ['brand_id' => $brand->id]);

        try {
            $data = $this->ga4Service->getDataApiData($brand);
            
            // Store in database
            $date = Carbon::yesterday();
            $this->storeAnalytics($brand, $date, $data);
            
            return $data;
        } catch (\Exception $e) {
            Log::error('MarketingAgent: Analytics collection failed', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Store analytics data.
     */
    protected function storeAnalytics(Brand $brand, Carbon $date, array $data): void
    {
        $metrics = [
            'visitors' => $data['visitors'] ?? 0,
            'page_views' => $data['page_views'] ?? 0,
            'sessions' => $data['sessions'] ?? 0,
            'conversions' => $data['conversions'] ?? 0,
            'revenue' => $data['revenue'] ?? 0,
        ];

        foreach ($metrics as $metric => $value) {
            AnalyticsSnapshot::updateOrCreate(
                [
                    'brand_id' => $brand->id,
                    'date' => $date->toDateString(),
                    'source' => 'ga4',
                    'metric' => $metric,
                    'dimension' => null,
                ],
                ['value' => $value]
            );
        }

        // Store top pages
        if (!empty($data['top_pages'])) {
            foreach ($data['top_pages'] as $page) {
                AnalyticsSnapshot::updateOrCreate(
                    [
                        'brand_id' => $brand->id,
                        'date' => $date->toDateString(),
                        'source' => 'ga4',
                        'metric' => 'visitors',
                        'dimension' => $page['path'] ?? '/',
                    ],
                    ['value' => $page['visitors'] ?? 0]
                );
            }
        }
    }

    /**
     * Analyze the collected data.
     */
    protected function analyzeData(array $analytics, Brand $brand): array
    {
        Log::info('MarketingAgent: Analyzing data', ['brand_id' => $brand->id]);

        // Prepare analysis using Strands tools
        $analysis = $this->generate([
            'task' => 'analyze_marketing_data',
            'brand' => [
                'name' => $brand->name,
                'domain' => $brand->domain_type,
            ],
            'analytics' => $analytics,
            'instructions' => 'Analyze this marketing data and identify:
                1. Key trends and patterns
                2. Opportunities for improvement
                3. Revenue leaks
                4. High-performing content
                5. Recommendations',
        ]);

        return [
            'raw_analysis' => $analysis,
            'timestamp' => Carbon::now()->toDateTimeString(),
        ];
    }

    /**
     * Generate a brief from the analysis.
     */
    protected function generateBrief(array $analysis, Brand $brand): array
    {
        Log::info('MarketingAgent: Generating brief', ['brand_id' => $brand->id]);

        $brief = $this->generate([
            'task' => 'generate_marketing_brief',
            'brand' => [
                'name' => $brand->name,
                'voice' => $brand->brand_voice,
                'domain' => $brand->domain_type,
            ],
            'analysis' => $analysis,
            'instructions' => 'Create a strategic marketing brief with:
                1. Executive summary
                2. Key findings
                3. Strategic recommendations
                4. Action items with priorities
                5. Estimated revenue impact',
        ]);

        // Store the brief
        $aiBrief = AiBrief::create([
            'brand_id' => $brand->id,
            'brief_date' => Carbon::today(),
            'fingerprint' => md5(json_encode($brief) . $brand->id),
            'strategic_diagnosis' => $brief['strategic_diagnosis'] ?? 'No diagnosis',
            'estimated_revenue_impact' => $brief['estimated_revenue_impact'] ?? 0,
            'confidence_score' => $brief['confidence_score'] ?? 80,
            'raw_llm_output' => $brief,
            'ai_provider' => 'strands',
            'model_used' => 'agent',
        ]);

        return [
            'id' => $aiBrief->id,
            'diagnosis' => $aiBrief->strategic_diagnosis,
            'revenue_impact' => $aiBrief->estimated_revenue_impact,
            'confidence' => $aiBrief->confidence_score,
        ];
    }

    /**
     * Create actions from the brief.
     */
    protected function createActions(array $brief, Brand $brand): array
    {
        Log::info('MarketingAgent: Creating actions', ['brand_id' => $brand->id]);

        $actions = [];
        $actionData = $brief['actions'] ?? [];

        foreach ($actionData as $data) {
            $action = AiAction::create([
                'brand_id' => $brand->id,
                'brief_id' => $brief['id'] ?? null,
                'title' => $data['title'] ?? 'Untitled Action',
                'category' => $data['category'] ?? 'strategy',
                'description' => $data['description'] ?? '',
                'suggested_content' => $data['suggested_content'] ?? null,
                'estimated_impact' => $data['estimated_impact'] ?? 0,
                'priority' => $data['priority'] ?? 3,
                'status' => 'pending',
            ]);

            $actions[] = [
                'id' => $action->id,
                'title' => $action->title,
                'category' => $action->category,
                'priority' => $action->priority,
            ];
        }

        return $actions;
    }

    /**
     * Generate content for approved actions.
     */
    protected function generateContent(array $actions, Brand $brand): array
    {
        Log::info('MarketingAgent: Generating content', ['brand_id' => $brand->id]);

        $content = [];

        foreach ($actions as $actionData) {
            if (($actionData['status'] ?? 'pending') !== 'approved') {
                continue;
            }

            $action = AiAction::find($actionData['id']);
            if (!$action) {
                continue;
            }

            // Generate content using the agent
            $draft = $this->generate([
                'task' => 'generate_content',
                'brand' => [
                    'name' => $brand->name,
                    'voice' => $brand->brand_voice,
                ],
                'action' => [
                    'title' => $action->title,
                    'description' => $action->description,
                    'category' => $action->category,
                ],
                'instructions' => 'Create content for this marketing action. Include:
                    1. Full content (blog post, social post, email, etc.)
                    2. SEO metadata
                    3. Call to action
                    4. Target keywords',
            ]);

            $content[] = [
                'action_id' => $action->id,
                'draft' => $draft,
            ];
        }

        return $content;
    }

    /**
     * Get the status of the agent.
     */
    public function getStatus(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'status' => 'ready',
            'tools' => array_map(function ($tool) {
                return get_class($tool);
            }, $this->tools),
            'timestamp' => Carbon::now()->toDateTimeString(),
        ];
    }
}