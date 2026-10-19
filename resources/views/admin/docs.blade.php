@extends('layouts.app')

@section('title', 'System Docs')

@php
    $toc = [
        'overview' => '1. The big picture',
        'repos' => '2. Where the code lives',
        'cycle' => '3. One agent cycle, step by step',
        'data' => '4. Data in: collectors and schedule',
        'evidence' => '5. What the agents are shown',
        'decide' => '6. Deciding: specialists, calibration, safety',
        'act' => '7. Acting, verifying, learning',
        'api' => '8. The agent API and its security',
        'pages' => '9. The web app',
        'ops' => '10. Running it',
        'rules' => '11. Rules this codebase lives by',
        'extend' => '12. How to extend it',
    ];
    $h2 = 'text-xl font-bold text-gray-900 mt-10 mb-3 pt-2 border-t';
    $h3 = 'text-base font-semibold text-gray-900 mt-5 mb-1';
    $p  = 'text-sm text-gray-700 leading-relaxed mb-2';
    $th = 'text-left px-3 py-2 bg-gray-50 text-xs uppercase text-gray-500';
    $td = 'px-3 py-2 border-t align-top';
    $code = 'bg-gray-100 rounded px-1 text-xs';
@endphp

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-5xl">
    <h1 class="text-2xl font-bold text-gray-900">📚 Vumbi AI: how the system works</h1>
    <p class="{{ $p }}">For engineers joining the project. Super-admins only. It describes the code as it is, with file paths, so you can open the file next to the text. Two repositories work together: <code class="{{ $code }}">marketting-app</code> (Laravel) and <code class="{{ $code }}">Autonomous-Agent</code> (Python).</p>

    <nav class="my-4 p-4 bg-gray-50 border rounded-lg text-sm grid sm:grid-cols-2 gap-x-6 gap-y-1">
        @foreach($toc as $id => $label)<a href="#{{ $id }}" class="text-blue-700 hover:underline">{{ $label }}</a>@endforeach
    </nav>

    {{-- 1 --}}
    <h2 id="overview" class="{{ $h2 }}">{{ $toc['overview'] }}</h2>
    <p class="{{ $p }}">The system is a marketing assistant for several brands. It is split into a <strong>body</strong> and a <strong>brain</strong>, with a person in charge:</p>
    <ul class="list-disc ml-6 {{ $p }}">
        <li><strong>Laravel (the body, the system of record).</strong> Collects data (GA4, Ahrefs, competitors, social listening), stores everything, shows pages to humans, exposes a secured JSON API, and carries out actions (write content, pause a campaign, change a budget).</li>
        <li><strong>Python agent (the brain).</strong> Every 15 minutes it asks Laravel what needs attention, shows specialist AI agents the real data, decides what to do, applies safety rules, and either acts or asks a human. It then checks the result and learns from it.</li>
        <li><strong>The human.</strong> Approves risky actions in the web app. Posts to social media by hand (the system never publishes posts).</li>
    </ul>
    <pre class="bg-gray-900 text-gray-100 text-xs rounded-lg p-4 overflow-x-auto">Collectors (scheduled jobs)  ──►  MySQL  ◄──  Web app pages (humans)
 GA4, Ahrefs, competitors,            │
 listening, SEO scans, leads          │  /api/agent/*  (API key)
                                       ▼
                         Python agent, every 15 min
   discover ► evidence ► specialist reasons ► calibrate ► safety gate
        ► execute (or ask human) ► verify (1h, 1d) ► learn ► repeat</pre>
    <p class="{{ $p }}">Two ideas hold the design together. First, <strong>honesty about data</strong>: unknown is never shown as zero. Second, <strong>safe autonomy</strong>: the agent can only do what a deterministic policy allows, risky actions need a human, every action is checked afterwards and can be reversed.</p>

    {{-- 2 --}}
    <h2 id="repos" class="{{ $h2 }}">{{ $toc['repos'] }}</h2>
    <h3 class="{{ $h3 }}">marketting-app (Laravel)</h3>
    <table class="w-full text-sm border rounded-lg mb-3"><thead><tr><th class="{{ $th }}">Path</th><th class="{{ $th }}">What it does</th></tr></thead><tbody>
        @foreach([
            ['routes/web.php', 'Human pages (login required). Page groups: brands, analytics, briefs, actions, content, seo, campaigns, listening, competitors, personas, content-plan, ad-platforms, leads, ahrefs, scanner, schedule, system.'],
            ['routes/api.php', 'The agent API under /api/agent/*, protected by verify.api.key, agent.brand and throttle:agent.'],
            ['app/Console/Kernel.php', 'The schedule: every collector and background job with its time.'],
            ['app/Jobs/*', 'ProcessAll… jobs loop over brands and call a service. Keep them thin.'],
            ['app/Services/*', 'The real logic. Analytics (GA4, AgentEvidenceService), Ahrefs, Competitors, Listening, Personas, ContentPlan, AdPlatforms, AI (gateway, briefs, content, approval), Campaign, Lead, Guardian (health), Schedule, Seo, Scanner.'],
            ['app/Http/Controllers/AgentController.php', 'All agent API endpoints (large). Opportunities, actions, verification, rollback, calibration, evidence.'],
            ['app/Http/Middleware/*', 'VerifyApiKey (shared or per-brand key), AgentBrandAuthorized, MonitorAgentIP, RequireSuperAdmin.'],
            ['app/Models/*', 'Eloquent models. AiAction is the central record of every proposed or executed action.'],
            ['config/ad_platforms.php, config/agent.php, config/services.php', 'Ad platform drivers, agent API keys, Ahrefs and other third-party settings.'],
            ['tests/Unit/*', 'Framework-free PHPUnit tests (no app boot). Run one with: php vendor/bin/phpunit tests/Unit/SomeTest.php'],
        ] as [$a, $b])<tr><td class="{{ $td }}"><code class="{{ $code }}">{{ $a }}</code></td><td class="{{ $td }}">{{ $b }}</td></tr>@endforeach
    </tbody></table>
    <h3 class="{{ $h3 }}">Autonomous-Agent (Python, Strands SDK)</h3>
    <table class="w-full text-sm border rounded-lg mb-3"><thead><tr><th class="{{ $th }}">Path</th><th class="{{ $th }}">What it does</th></tr></thead><tbody>
        @foreach([
            ['src/main.py', 'Entry point. Runs one cycle per brand, then sleeps AGENT_INTERVAL (900 s). Brands come from AGENT_BRANDS (all, a list, or BRAND_ID). A per-brand file lock stops two cycles overlapping.'],
            ['src/orchestrator.py', 'The supervisor. run_cycle() is the whole loop (section 3). Also priority ranking, escalation of recurring issues, outcome handling.'],
            ['src/specialists/{seo,leads,content,analytics}.py', 'One specialist per opportunity type. base.py holds the reasoning prompt, the LLM call and compact_evidence().'],
            ['src/policies/safety.py', 'SafetyPolicy.evaluate(): the deterministic gate that can override the AI.'],
            ['src/services/calibration.py', 'Adjusts the AI’s stated confidence using measured past accuracy.'],
            ['src/executor.py', 'Routes an approved decision to the right Laravel endpoint.'],
            ['src/verifier.py', 'Snapshots metrics before, re-checks after 1 hour and 1 day, decides success, requests rollback.'],
            ['src/learner.py, src/memory/experience.py', 'Records every outcome and finds similar past cases.'],
            ['src/utils/api_client.py', 'LaravelApiClient: every HTTP call to Laravel, with safe retries.'],
            ['src/utils/{fingerprints,lock}.py', 'Idempotency keys and the per-brand cycle lock.'],
        ] as [$a, $b])<tr><td class="{{ $td }}"><code class="{{ $code }}">{{ $a }}</code></td><td class="{{ $td }}">{{ $b }}</td></tr>@endforeach
    </tbody></table>

    {{-- 3 --}}
    <h2 id="cycle" class="{{ $h2 }}">{{ $toc['cycle'] }}</h2>
    <p class="{{ $p }}">Everything the agent does is in <code class="{{ $code }}">Orchestrator.run_cycle()</code> (src/orchestrator.py). In order:</p>
    <ol class="list-decimal ml-6 {{ $p }} space-y-1">
        <li><strong>Freshness check.</strong> Asks Laravel whether today’s data is fresh (<code class="{{ $code }}">/agent/data-status</code>). If not, asks Laravel to refresh in the background (<code class="{{ $code }}">/agent/refresh-data</code>). A failure here is logged and the cycle continues.</li>
        <li><strong>Daily brief and tours.</strong> Loads the AI-written strategic brief and the tour catalogue into the cycle context.</li>
        <li><strong>Human outcomes.</strong> Picks up what humans did since the last cycle (approved, rejected, responded to an escalation) and feeds it to the learner.</li>
        <li><strong>Due verifications.</strong> Runs the 1-hour and 1-day checks on past actions (section 7).</li>
        <li><strong>Discovery.</strong> Fetches opportunities (<code class="{{ $code }}">GET /agent/opportunities/{brand}</code>). Laravel builds them in four types, each with its own specialist: <code class="{{ $code }}">seo_issue</code> (open SEO issues, ranking drops and lost backlinks from real Ahrefs data), <code class="{{ $code }}">leads_pending</code>, <code class="{{ $code }}">analytics_alert</code> and <code class="{{ $code }}">content_generation</code>. None found: the cycle ends as “idle”.</li>
        <li><strong>Idempotency.</strong> Each opportunity gets a fingerprint (<code class="{{ $code }}">src/utils/fingerprints.py</code>). Anything already handled today is skipped, so a cycle that repeats does not repeat work. Recurring issues are tracked by a stable key and can be escalated to a human.</li>
        <li><strong>Evidence snapshot.</strong> Gathers analytics, SEO issues, leads, campaigns and the combined evidence once per cycle, in parallel (section 5).</li>
        <li><strong>Rank and limit.</strong> Opportunities are ranked by priority; only <code class="{{ $code }}">MAX_ACTIONS_PER_CYCLE</code> (default 10) are processed, the rest wait for the next cycle.</li>
        <li><strong>Process each opportunity</strong>, up to <code class="{{ $code }}">AGENT_CONCURRENCY</code> (default 3) at a time, through <code class="{{ $code }}">_process_opportunity()</code>:
            <ol class="list-[lower-alpha] ml-6 mt-1">
                <li>The matching specialist <em>reasons</em> over the opportunity, the evidence and similar past cases. It returns an action, a confidence from 0 to 1, reasoning and an estimated impact.</li>
                <li><em>Calibration</em> nudges the confidence toward what the agent has really achieved at that confidence level (section 6).</li>
                <li>The recent count of that action type is fetched so the frequency limit can be enforced.</li>
                <li>The <em>safety policy</em> decides: autonomous, or needs a human.</li>
                <li>Autonomous: the <em>executor</em> calls Laravel. Otherwise the result is “requires_approval” and the action appears in the approval queue in the web app.</li>
                <li>If something executed, a <em>verification</em> is scheduled.</li>
                <li>The <em>learner</em> records the whole case, even if recording fails (failure is only logged).</li>
            </ol></li>
        <li><strong>Report.</strong> A cycle summary is sent back to Laravel for the system pages.</li>
    </ol>
    <p class="{{ $p }}">Failure behaviour: each early step is wrapped, so a failure in freshness, brief, tours, outcomes or verification is logged and the cycle carries on. A failure for one brand never stops the others.</p>

    {{-- 4 --}}
    <h2 id="data" class="{{ $h2 }}">{{ $toc['data'] }}</h2>
    <p class="{{ $p }}">Laravel’s scheduler (<code class="{{ $code }}">app/Console/Kernel.php</code>) runs these. Times are the server’s timezone.</p>
    <table class="w-full text-sm border rounded-lg mb-3"><thead><tr><th class="{{ $th }}">When</th><th class="{{ $th }}">Job</th><th class="{{ $th }}">Purpose</th></tr></thead><tbody>
        @foreach([
            ['Every 5 min', 'ProcessAllApprovedActionsJob', 'Carries out human-approved actions (content generation).'],
            ['Every 15 min', 'ProcessAllBrandsLeadsJob', 'Scores and processes new leads.'],
            ['Hourly', 'ProcessAllHealthChecksJob (emergency)', 'Guardian health checks.'],
            ['Sun 04:00', 'ProcessAllBrandsSiteScansJob', 'Page inventory used for content generation.'],
            ['Daily 05:30', 'ProcessAllBrandsAnalyticsJob', 'GA4 data into analytics_snapshots.'],
            ['Daily 06:00', 'ProcessAllBrandsBriefsJob', 'AI daily brief per brand.'],
            ['Mon 06:00', 'ProcessAllCompetitorsJob', 'Scans competitor homepages, sitemaps and blog feeds.'],
            ['Daily 07:00', 'ProcessAllSeoChecksJob', 'SEO issue detection.'],
            ['Daily 07:30', 'ProcessAllAhrefsDataJob', 'Ahrefs site stats, keywords, backlinks.'],
            ['Daily 08:00', 'ProcessAllBrandsCampaignsJob', 'Campaign performance.'],
            ['Daily 08:00', 'ProcessAllListeningJob', 'Social listening checks (news, Reddit, Hacker News).'],
            ['Daily 08:30', 'ProcessAllAffiliateDataJob', 'Affiliate data.'],
            ['Daily 09:30 / 10:00', 'Health checks / lead follow-ups', 'Guardian and lead follow-up tasks.'],
        ] as [$a, $b, $c])<tr><td class="{{ $td }}">{{ $a }}</td><td class="{{ $td }}"><code class="{{ $code }}">{{ $b }}</code></td><td class="{{ $td }}">{{ $c }}</td></tr>@endforeach
    </tbody></table>
    <p class="{{ $p }}">Run <code class="{{ $code }}">php artisan schedule:check</code> to see the schedule status. The server needs a cron entry that runs <code class="{{ $code }}">php artisan schedule:run</code> every minute, and a queue worker for the jobs.</p>

    <h3 class="{{ $h3 }}">The data sources</h3>
    <table class="w-full text-sm border rounded-lg mb-3"><thead><tr><th class="{{ $th }}">Source</th><th class="{{ $th }}">How it works</th><th class="{{ $th }}">Key files</th></tr></thead><tbody>
        @foreach([
            ['GA4 (analytics)', 'Stored long-format in analytics_snapshots: a null dimension is the brand total, a page URL is a per-page row, a channel name carries channel_* metrics. History older than 30 days needs php artisan analytics:backfill-ga4.', 'Services/Analytics/GA4Service.php, GA4ReportParser'],
            ['Ahrefs (SEO)', 'API v3 Site Explorer: domain rating, metrics, backlink stats, organic keywords with position change, backlinks including lost ones. Each section stores independently; a failed section stores nothing, never zeros. Diagnose with php artisan ahrefs:diagnose.', 'Services/Ahrefs/AhrefsApi.php, AhrefsService.php'],
            ['Competitors', 'Free: reads each competitor’s homepage, robots.txt, sitemap and RSS feed. Computes posts in the last 30 days and topic gaps against our titles and keywords. Has an SSRF guard (public IPs only, redirects re-validated, DNS pinned, 2 MB cap, XXE-safe XML).', 'Services/Competitors/*'],
            ['Social listening', 'Free: Google News RSS, Reddit search (often blocked, shown as “blocked”), Hacker News. States: no_queries, never_checked, no_mentions_yet, active. “No mentions yet” is a baseline, not a drop. Sentiment is a simple word-list heuristic and is labelled that way.', 'Services/Listening/*'],
            ['Buyer personas', 'Written by a human. Confidence is earned: 3 direct sources (interviews, surveys, real conversations) = emerging, 10 = validated, inferred signals alone stay hypothesis, old evidence is flagged.', 'Services/Personas/*'],
            ['Content plan', 'Suggestions from keywords ranking 4–20, competitor gaps, listening conversations and persona pains. A human posts and records results. Performance names a winner only with 8+ measured posts and 3+ in a group.', 'Services/ContentPlan/*'],
        ] as [$a, $b, $c])<tr><td class="{{ $td }}"><strong>{{ $a }}</strong></td><td class="{{ $td }}">{{ $b }}</td><td class="{{ $td }}"><code class="{{ $code }}">{{ $c }}</code></td></tr>@endforeach
    </tbody></table>

    {{-- 5 --}}
    <h2 id="evidence" class="{{ $h2 }}">{{ $toc['evidence'] }}</h2>
    <p class="{{ $p }}">One call, <code class="{{ $code }}">GET /api/agent/evidence/{brandId}</code>, returns everything the specialists should reason over. It is built by <code class="{{ $code }}">AgentEvidenceService::build()</code> and has these sections: <code class="{{ $code }}">analytics</code> (with channels and period-over-period changes), <code class="{{ $code }}">seo</code> (authority, keyword movers, striking-distance keywords at positions 4–20, backlinks gained and lost, open issues), <code class="{{ $code }}">competitors</code>, <code class="{{ $code }}">listening</code>, <code class="{{ $code }}">personas</code>, <code class="{{ $code }}">content</code> and <code class="{{ $code }}">data_quality</code>.</p>
    <p class="{{ $p }}">Each optional section is wrapped so that if it fails, it becomes null and the endpoint still answers. A unit test checks that every method <code class="{{ $code }}">build()</code> calls exists, because a missing one would crash the endpoint for every brand.</p>
    <p class="{{ $p }}">On the Python side, <code class="{{ $code }}">compact_evidence()</code> in <code class="{{ $code }}">src/specialists/base.py</code> trims this to what fits in a prompt (top items only) and passes it to the model as <em>real_data</em>. The prompt instructs the model to rely on that data and to treat missing data as unknown.</p>

    {{-- 6 --}}
    <h2 id="decide" class="{{ $h2 }}">{{ $toc['decide'] }}</h2>
    <h3 class="{{ $h3 }}">Specialists</h3>
    <p class="{{ $p }}">Each specialist wraps an LLM (Gemini by default, a local model is configurable) with a domain prompt and tools. <code class="{{ $code }}">reason()</code> builds the prompt from the opportunity, evidence, brief and past similar cases, requests a structured decision (action name, JSON payload, confidence, reasoning, estimated impact) and falls back to a safe decision if the model’s answer cannot be parsed.</p>
    <h3 class="{{ $h3 }}">Calibration</h3>
    <p class="{{ $p }}">Models are often over-confident. <code class="{{ $code }}">CalibrationService.adjust()</code> puts the stated confidence into a bucket (0.0–1.0 in tenths), looks up how accurate the agent really was in that bucket for that opportunity type, and moves the confidence toward it. The shift is capped at 0.30 and only used when there are enough samples. Otherwise the stated value is kept and the reason says so.</p>
    <h3 class="{{ $h3 }}">The safety gate</h3>
    <p class="{{ $p }}"><code class="{{ $code }}">SafetyPolicy.evaluate()</code> is plain code, not AI, and always has the last word. It checks, in order: is the action known (unknown means human review), is the brand allowed, does the estimated impact exceed the action’s cap, has the hourly frequency limit been reached, and, for actions that need reasoning, is confidence at or above <code class="{{ $code }}">AUTONOMOUS_THRESHOLD</code> (0.8). Only if every check passes, and the action does not require approval, is it autonomous.</p>
    <table class="w-full text-sm border rounded-lg mb-3"><thead><tr><th class="{{ $th }}">Action</th><th class="{{ $th }}">Needs approval</th><th class="{{ $th }}">Max per hour</th><th class="{{ $th }}">Reversible</th><th class="{{ $th }}">Risk</th></tr></thead><tbody>
        @foreach([
            ['resolve_seo_issue', 'Yes', '50', 'Yes', 'low'],
            ['run_site_scan', 'No', '10', 'Yes', 'low'],
            ['trigger_content_generation', 'Yes', '5', 'No', 'medium'],
            ['create_blog_post / generate_content', 'No (needs confidence)', '—', 'No', 'medium'],
            ['notify_lead_response', 'No (needs confidence)', '20', 'No', 'medium'],
            ['pause_campaign', 'Yes', '3 (impact cap $5000)', 'Yes', 'high'],
            ['adjust_campaign', 'Yes', '3', 'Yes', 'high'],
            ['escalate_recurring_issue', 'No', '10', 'Yes', 'low'],
            ['no_action_needed', 'No', '—', 'Yes', 'low'],
        ] as $r)<tr>@foreach($r as $c)<td class="{{ $td }}">{{ $c }}</td>@endforeach</tr>@endforeach
    </tbody></table>
    <p class="{{ $p }}">To change what the agent may do alone, edit this table in <code class="{{ $code }}">src/policies/safety.py</code>. Anything not listed there always goes to a human.</p>

    {{-- 7 --}}
    <h2 id="act" class="{{ $h2 }}">{{ $toc['act'] }}</h2>
    <h3 class="{{ $h3 }}">Executing</h3>
    <p class="{{ $p }}"><code class="{{ $code }}">Executor._route_action()</code> maps an action name to a Laravel endpoint: content actions to content generation, notify_lead_response to the lead follow-up, pause_campaign and adjust_campaign to the campaign endpoints, SEO actions to the SEO and scan endpoints. The result records the <code class="{{ $code }}">action_id</code> (the AiAction row) so it can be verified. Timeouts and errors are returned as statuses, not thrown.</p>
    <p class="{{ $p }}">Human approval path: actions that need approval appear in the Actions queue page. Approving runs <code class="{{ $code }}">ActionApprovalService</code>; content actions are picked up by the 5-minute job.</p>
    <h3 class="{{ $h3 }}">Verifying</h3>
    <p class="{{ $p }}">Right after executing, the verifier snapshots the relevant metrics (<code class="{{ $code }}">/agent/metrics</code>) and registers a verification with the stated confidence (<code class="{{ $code }}">/agent/verification/register</code>). Each cycle it asks which verifications are due (<code class="{{ $code }}">/agent/verification/due</code>) and re-measures at <strong>1 hour</strong> and <strong>1 day</strong>, computes deltas, and scores the improvement. If the action made things worse it requests a rollback. <code class="{{ $code }}">decide_success()</code> returns true or false, or <em>inconclusive</em> when a human also touched the same thing, because then the result cannot be credited to the agent.</p>
    <h3 class="{{ $h3 }}">Rollback</h3>
    <p class="{{ $p }}">Reversible actions store their previous state in <code class="{{ $code }}">reversal_data</code> before acting, so a rollback restores the real previous state. Rollbacks are requested through <code class="{{ $code }}">/agent/actions/{id}/request-rollback</code> and approved by a human.</p>
    <h3 class="{{ $h3 }}">Learning</h3>
    <p class="{{ $p }}">The learner stores each case (opportunity, decision, result, verification) as an experience. Next time a similar opportunity appears, <code class="{{ $code }}">ExperienceMemory.analyze_patterns()</code> tells the specialist what worked and failed before. Verified outcomes also feed the calibration buckets.</p>
    <h3 class="{{ $h3 }}">Meta organic: Pages, Instagram, insights, publishing</h3>
    <p class="{{ $p }}">Everything lives in <code class="{{ $code }}">app/Services/Meta</code>, configured by <code class="{{ $code }}">config/meta.php</code> (token from <code class="{{ $code }}">META_ACCESS_TOKEN</code>, falling back to the ads token). <code class="{{ $code }}">GraphClient</code> sends the token in a header and raises a classified <code class="{{ $code }}">MetaApiException</code> (token expired, missing permission, rate limit, invalid parameter). <code class="{{ $code }}">MetaCapabilities</code> maps every free capability to the permission that unlocks it; the <strong>Meta Connection</strong> page reads the token’s real scopes with <code class="{{ $code }}">debug_token</code> and shows what is available and what to add.</p>
    <ul class="list-disc ml-6 {{ $p }}">
        <li><strong>Results sync</strong> (<code class="{{ $code }}">ResultsSync</code>, daily 09:15 or the button): imports your recent Facebook and Instagram posts, including ones you posted by hand, as posted Content Plan items, then refreshes views, reach, engagements, clicks and shares. Meta retired most reach and impression metrics in June 2026; metric names are in config, a metric Meta rejects is skipped and reported, and a number Meta did not return stays unknown.</li>
        <li><strong>Auto-posting</strong> (<code class="{{ $code }}">ContentPublisher</code>, checked every 5 minutes) is off unless <code class="{{ $code }}">META_PUBLISHING_ENABLED=true</code> and only posts text a human approved for a set time. There is a daily cap per brand. A post is claimed before the API call and never retried automatically, so a timeout cannot double-post; a failure is recorded on the item with Meta’s reason. Facebook supports text, link and photo; Instagram supports photos from a public https image URL. Video and Reels remain manual.</li>
        <li>Manual posting still works the same way: copy the draft, post it, click “I posted this”.</li>
        <li><strong>Ad performance by audience</strong> (<code class="{{ $code }}">AdInsightsSync</code>, daily 06:30): reads the Marketing API Insights endpoint for the last 30 days, in five cuts (total, campaign, age and gender, country, placement) into <code class="{{ $code }}">ad_insight_rows</code>. A cut that fails keeps its previous rows. <code class="{{ $code }}">AdAudienceInsights</code> ranks groups by click rate only when there are at least 1,000 impressions overall and 500 in the group. The ad account comes from <code class="{{ $code }}">META_AD_ACCOUNT_ID</code>, the brand’s <code class="{{ $code }}">meta_ad_account_id</code>, or the only account the token can see.</li>
        <li><strong>Customer comments</strong> (<code class="{{ $code }}">CommentSync</code>, every 30 minutes): saves what people write under your recent Facebook and Instagram posts into <code class="{{ $code }}">audience_comments</code> with a rough sentiment and a question flag. Author names are not stored.</li>
        <li><strong>Lead ad leads</strong> (<code class="{{ $code }}">LeadSync</code>, every 30 minutes): pulls form leads into <code class="{{ $code }}">leads</code> (source social), keeping Meta’s lead id in metadata so nothing duplicates. The existing lead pipeline and the agent’s pending-leads check take over from there.</li>
        <li><strong>Audience research</strong> (<code class="{{ $code }}">AudienceResearch</code>): free interest search with Meta’s audience-size ranges, and a reach estimate for a country. <code class="{{ $code }}">php artisan meta:sync-data --only=ads,comments,leads</code> runs the pulls by hand.</li>
    </ul>
    <h3 class="{{ $h3 }}">Ad platforms</h3>
    <p class="{{ $p }}">Ad changes go through <code class="{{ $code }}">App\Services\AdPlatforms\AdPlatformInterface</code> (name, isConfigured, fetchCampaign, pause, resume, setDailyBudget). Drivers are listed in <code class="{{ $code }}">config/ad_platforms.php</code>; Meta uses the Graph API with credentials only from the environment. Budget changes are capped at 25% per action, state is captured before acting, and a rollback truly reverses it. Add a platform by implementing the interface and adding one line to the config.</p>

    {{-- 8 --}}
    <h2 id="api" class="{{ $h2 }}">{{ $toc['api'] }}</h2>
    <p class="{{ $p }}">Every route under <code class="{{ $code }}">/api/agent</code> goes through three middleware, in this order:</p>
    <ol class="list-decimal ml-6 {{ $p }}">
        <li><code class="{{ $code }}">verify.api.key</code>: reads the <code class="{{ $code }}">X-API-Key</code> header. It accepts either the shared key (<code class="{{ $code }}">LARAVEL_API_KEY</code>, all brands, can be disabled with <code class="{{ $code }}">AGENT_ALLOW_SHARED_KEY=false</code>) or a per-brand key, stored only as a SHA-256 hash on the brand. A brand key is refused (403) on any request that names a different brand or an action belonging to another brand. Issue or revoke one with <code class="{{ $code }}">php artisan agent:issue-key {brand} [--revoke]</code>.</li>
        <li><code class="{{ $code }}">agent.brand</code>: resolves the brand from the route, the body, or the action being touched, and rejects unknown brands or actions.</li>
        <li><code class="{{ $code }}">throttle:agent</code>: 300 requests per minute per key, and 60 write requests per minute. Content generation has its own 20 per minute limit.</li>
    </ol>
    <p class="{{ $p }}">Route groups: health (<code class="{{ $code }}">ai/ping</code>), data freshness, evidence, brief, tours, opportunities (list, check, mark, history, resolve), analytics, SEO (issues, rankings, recommendations, analyze), leads, campaigns (list, pause, adjust), content (generate, outline, gap-analysis), actions (execute, rollback, outcomes, acknowledge, authorize-retry, count), metrics, escalations, calibration and verification. Open <code class="{{ $code }}">routes/api.php</code> for the full list; each route names its AgentController method.</p>

    {{-- 9 --}}
    <h2 id="pages" class="{{ $h2 }}">{{ $toc['pages'] }}</h2>
    <p class="{{ $p }}">The sidebar is <code class="{{ $code }}">resources/views/components/aside.blade.php</code>, built from <code class="{{ $code }}">&lt;x-sidebar-link&gt;</code> entries. Adding a page means adding a route, a controller method, a view and one sidebar entry. Pages the system exposes: Analytics and revenue leaks, Briefs, Actions queue, Content and drafts, SEO, Ahrefs (with ranking movement and lost backlinks), Competitors, Social Listening, Buyer Personas, Content Plan, Campaigns, Ad Platforms, Leads, Schedule monitor, System health and Guides. Specific rules:</p>
    <ul class="list-disc ml-6 {{ $p }}">
        <li>Every numeric display must show “—” for unknown, never 0.</li>
        <li>Numeric route parameters use <code class="{{ $code }}">->whereNumber()</code> so that literal routes such as <code class="{{ $code }}">/blog/import</code> are not swallowed by <code class="{{ $code }}">/blog/{id}</code>.</li>
        <li>This page and <code class="{{ $code }}">/admin/docs</code> require the <code class="{{ $code }}">superadmin</code> middleware (<code class="{{ $code }}">RequireSuperAdmin</code>). Grant access with <code class="{{ $code }}">php artisan user:make-super-admin you@example.com</code>.</li>
    </ul>

    {{-- 10 --}}
    <h2 id="ops" class="{{ $h2 }}">{{ $toc['ops'] }}</h2>
    <h3 class="{{ $h3 }}">Laravel</h3>
    <pre class="bg-gray-900 text-gray-100 text-xs rounded-lg p-4 overflow-x-auto">composer install
cp .env.example .env     # then fill in the values below
php artisan key:generate
php artisan migrate
php artisan user:make-super-admin you@example.com
# server cron:   * * * * * php artisan schedule:run
# queue worker:  php artisan queue:work</pre>
    <table class="w-full text-sm border rounded-lg mb-3"><thead><tr><th class="{{ $th }}">Variable</th><th class="{{ $th }}">Meaning</th></tr></thead><tbody>
        @foreach([
            ['LARAVEL_API_KEY / AGENT_ALLOW_SHARED_KEY', 'Shared agent API key; set the second to false once every agent uses per-brand keys.'],
            ['AHREFS_API_TOKEN (or AHREFS_API_KEY), AHREFS_API_ENDPOINT, AHREFS_KEYWORD_LIMIT, AHREFS_BACKLINK_LIMIT', 'Ahrefs API v3. Ahrefs bills API units per row returned.'],
            ['META_AD_ACCOUNT_ID, META_AUDIENCE_COUNTRIES', 'Which ad account to report on, and the countries used when sizing an audience.'],
            ['META_ADS_ACCESS_TOKEN, META_ADS_API_VERSION, META_ADS_CURRENCY_OFFSET, AD_MAX_BUDGET_CHANGE', 'Meta Graph API for ads, and the 25% budget-change cap.'],
            ['GA4 and Gemini settings', 'See config/services.php and the AI gateway config.'],
        ] as [$a, $b])<tr><td class="{{ $td }}"><code class="{{ $code }}">{{ $a }}</code></td><td class="{{ $td }}">{{ $b }}</td></tr>@endforeach
    </tbody></table>
    <p class="{{ $p }}">After changing <code class="{{ $code }}">.env</code>, run <code class="{{ $code }}">php artisan config:clear</code>. Useful commands: <code class="{{ $code }}">analytics:backfill-ga4 {brand?} --days=90</code>, <code class="{{ $code }}">ahrefs:diagnose {brand?}</code>, <code class="{{ $code }}">competitors:scan {brand?}</code>, <code class="{{ $code }}">listening:collect {brand?}</code>, <code class="{{ $code }}">schedule:check</code>, <code class="{{ $code }}">agent:issue-key {brand}</code>, <code class="{{ $code }}">ai:test-gemini</code>, <code class="{{ $code }}">test:real-data</code>.</p>
    <h3 class="{{ $h3 }}">Python agent</h3>
    <pre class="bg-gray-900 text-gray-100 text-xs rounded-lg p-4 overflow-x-auto">pip install -r requirements.txt
export LARAVEL_API_URL=https://your-app/api LARAVEL_API_KEY=... GEMINI_API_KEY=...
export AGENT_BRANDS=all        # or 1,2,3 ; unset = BRAND_ID
python run.py                  # continuous mode, one cycle every AGENT_INTERVAL seconds
python -m pytest -q            # tests</pre>
    <p class="{{ $p }}">Settings in <code class="{{ $code }}">src/config.py</code>: <code class="{{ $code }}">AGENT_INTERVAL</code> (900), <code class="{{ $code }}">AUTONOMOUS_THRESHOLD</code> (0.8), <code class="{{ $code }}">MAX_ACTIONS_PER_CYCLE</code> (10), <code class="{{ $code }}">AGENT_CONCURRENCY</code> (3), <code class="{{ $code }}">GEMINI_MODEL</code>, <code class="{{ $code }}">OLLAMA_HOST/MODEL</code> and <code class="{{ $code }}">LOG_LEVEL</code>. Deployment notes are in <code class="{{ $code }}">deploy/</code>.</p>
    <h3 class="{{ $h3 }}">Known environment caveat</h3>
    <p class="{{ $p }}">The framework-free unit tests run on PHP 8.3, but booting Laravel needs the PHP version the lockfile’s Symfony packages require (8.4). Run feature tests and the app on a matching PHP version.</p>

    {{-- 11 --}}
    <h2 id="rules" class="{{ $h2 }}">{{ $toc['rules'] }}</h2>
    <ol class="list-decimal ml-6 {{ $p }} space-y-1">
        <li><strong>Unknown is not zero.</strong> Use null in the database and “—” in the UI. A failed fetch stores nothing. “No mentions yet” and “not measured” are states, not drops.</li>
        <li><strong>The AI proposes; code disposes.</strong> Anything that changes the outside world goes through SafetyPolicy, and risky actions need a human.</li>
        <li><strong>Capture state before acting.</strong> Reversible actions save <code class="{{ $code }}">reversal_data</code> first, so rollback is real.</li>
        <li><strong>Humans post to social media.</strong> The system plans, drafts and learns; it never publishes posts.</li>
        <li><strong>Optional sections must not take down required ones.</strong> Evidence sections are wrapped and become null on failure.</li>
        <li><strong>Be honest about weak data.</strong> Heuristics are labelled (sentiment), small samples say “too early”, personas without evidence say “hypothesis”.</li>
        <li><strong>Outbound fetches are guarded.</strong> The competitor scanner accepts only public IPs, re-validates redirects, caps response size and parses XML safely. Reuse it for any new user-supplied URL.</li>
        <li><strong>Brand isolation.</strong> Every query is scoped by brand id; per-brand API keys cannot touch other brands.</li>
        <li><strong>Tests that need no framework.</strong> Put logic in pure classes or small services so it is testable without booting Laravel. Use in-memory SQLite for database behaviour.</li>
        <li><strong>Gotchas already hit.</strong> <code class="{{ $code }}">whereDate()</code> on a datetime string fails on SQLite (convert with Carbon first). A model relation named <code class="{{ $code }}">query</code> clashes with Eloquent’s static <code class="{{ $code }}">query()</code>. Stage explicit paths in git, not everything, and check that a PR’s base branch is main before merging stacked work.</li>
    </ol>

    {{-- 12 --}}
    <h2 id="extend" class="{{ $h2 }}">{{ $toc['extend'] }}</h2>
    <table class="w-full text-sm border rounded-lg mb-3"><thead><tr><th class="{{ $th }}">I want to…</th><th class="{{ $th }}">Do this</th></tr></thead><tbody>
        @foreach([
            ['Add a new data source', 'Create a service under app/Services/<Name>, a scheduled ProcessAll job and a migration. Expose a summary() method, add a wrapped section to AgentEvidenceService::build(), then add a block in compact_evidence() (Python) with tests on both sides. Show it on a page and add a sidebar link.'],
            ['Add a new action the agent can take', 'Add an ActionPolicy in src/policies/safety.py (start with requires_approval=True), a route in Executor._route_action(), the Laravel endpoint, an entry in ACTION_CATEGORY, and the reversal logic if it is reversible.'],
            ['Add a new kind of opportunity', 'Produce it in AgentController::getOpportunities() (type, severity, payload, impact), add a specialist or teach an existing one in src/specialists, and register it in the orchestrator’s specialists map.'],
            ['Add an ad platform', 'Implement AdPlatformInterface, register the driver in config/ad_platforms.php, read its credentials from env. Keep actions human-approved and capture state before acting.'],
            ['Change how cautious the agent is', 'Edit AUTONOMOUS_THRESHOLD, the per-action limits in safety.py, or MAX_ACTIONS_PER_CYCLE. Prefer tightening limits over loosening them until verified outcomes support it.'],
            ['Debug "no data"', 'Run the matching command (ahrefs:diagnose, analytics:backfill-ga4, listening:collect, competitors:scan), then check data_quality in GET /agent/evidence/{brand}. It says which sections are missing and why.'],
        ] as [$a, $b])<tr><td class="{{ $td }}"><strong>{{ $a }}</strong></td><td class="{{ $td }}">{{ $b }}</td></tr>@endforeach
    </tbody></table>
    <p class="text-xs text-gray-400 mt-8">Written from the code on 18 Oct 2026. When you change behaviour described here, update this page in the same pull request.</p>
</div>
@endsection
