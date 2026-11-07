<?php

namespace Tests\Unit;

use App\Models\AdInsightRow;
use App\Models\AudienceComment;
use App\Models\Lead;
use App\Services\Meta\AdAudienceInsights;
use App\Services\Meta\AdInsightsSync;
use App\Services\Meta\AudienceResearch;
use App\Services\Meta\CommentSync;
use App\Services\Meta\LeadSync;
use App\Services\Meta\MetaAssets;
use App\Services\Meta\MetaCapabilities;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Http\Client\Request;
use Tests\Unit\Support\MetaTestCase;

class MetaDataTest extends MetaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::schema()->create('ad_insight_rows', function ($t) {
            $t->id(); $t->unsignedBigInteger('brand_id'); $t->string('ad_account_id'); $t->string('breakdown');
            $t->string('dim1')->nullable(); $t->string('dim2')->nullable(); $t->string('label')->nullable();
            $t->date('date_start'); $t->date('date_stop');
            $t->unsignedBigInteger('impressions')->nullable(); $t->unsignedBigInteger('reach')->nullable(); $t->unsignedBigInteger('clicks')->nullable();
            $t->decimal('spend', 14, 2)->nullable(); $t->unsignedInteger('results')->nullable(); $t->string('currency')->nullable();
            $t->string('row_hash'); $t->timestamps();
        });
        DB::schema()->create('audience_comments', function ($t) {
            $t->id(); $t->unsignedBigInteger('brand_id'); $t->string('platform'); $t->string('remote_id'); $t->string('post_remote_id')->nullable();
            $t->text('body'); $t->unsignedInteger('likes')->nullable(); $t->string('sentiment')->nullable(); $t->boolean('is_question')->default(false);
            $t->timestamp('commented_at')->nullable(); $t->timestamps();
        });
        DB::schema()->create('leads', function ($t) {
            $t->id(); $t->unsignedBigInteger('brand_id'); $t->string('first_name')->nullable(); $t->string('last_name')->nullable();
            $t->string('email')->nullable(); $t->string('phone')->nullable(); $t->string('company')->nullable(); $t->string('title')->nullable();
            $t->text('message')->nullable(); $t->string('source')->default('website'); $t->string('status')->default('new'); $t->text('metadata')->nullable(); $t->timestamps();
        });
        $this->setConfig('meta.ad_account_id', null);
    }

    private function assets(): MetaAssets
    {
        return new MetaAssets($this->graph());
    }

    private function pagesResponse(): array
    {
        return ['data' => [['id' => '77', 'name' => 'Vumbi', 'access_token' => 'page-token', 'tasks' => [], 'instagram_business_account' => ['id' => '88', 'username' => 'vumbi']]]];
    }

    // ---- capabilities -------------------------------------------------------------------------------

    public function test_recommended_scopes_cover_every_built_feature(): void
    {
        $rec = MetaCapabilities::recommendedScopes();
        foreach (['ads_read', 'leads_retrieval', 'pages_read_user_content', 'instagram_manage_comments', 'read_insights', 'pages_manage_posts'] as $s) {
            $this->assertContains($s, $rec);
        }
        $this->assertSame([], MetaCapabilities::missingForBuilt($rec));
    }

    // ---- ad insights ---------------------------------------------------------------------------------

    public function test_ad_row_parsing_keeps_unknown_as_null_and_does_not_double_count_leads(): void
    {
        $p = AdInsightsSync::parseRow(['impressions' => '1200', 'spend' => '12.50', 'age' => '25-34', 'gender' => 'female', 'account_currency' => 'USD',
            'actions' => [['action_type' => 'lead', 'value' => '3'], ['action_type' => 'onsite_conversion.lead_grouped', 'value' => '3']],
            'date_start' => '2026-09-19', 'date_stop' => '2026-10-18'], 'age_gender', ['age', 'gender']);
        $this->assertSame(1200, $p['impressions']);
        $this->assertNull($p['clicks']);
        $this->assertNull($p['reach']);
        $this->assertSame(12.5, $p['spend']);
        $this->assertSame(3, $p['results']);
        $this->assertSame(['25-34', 'female'], [$p['dim1'], $p['dim2']]);

        $none = AdInsightsSync::parseRow(['impressions' => '10'], 'total', []);
        $this->assertNull($none['results']);
        $this->assertNull($none['spend']);
    }

    public function test_sync_stores_rows_and_a_failed_breakdown_keeps_old_rows(): void
    {
        AdInsightRow::create(['brand_id' => 1, 'ad_account_id' => 'act_5', 'breakdown' => 'country', 'dim1' => 'KE', 'date_start' => '2026-08-01', 'date_stop' => '2026-08-30',
            'impressions' => 999, 'row_hash' => 'old']);

        $this->http->fake(function (Request $r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
            $b = $q['breakdowns'] ?? '';
            if ($b === 'country') {
                return $this->http->response(['error' => ['code' => 100, 'message' => 'Invalid parameter']], 400);
            }
            if ($b === 'age,gender') {
                return $this->http->response(['data' => [['age' => '25-34', 'gender' => 'male', 'impressions' => '800', 'clicks' => '8', 'spend' => '4.00', 'date_start' => '2026-09-19', 'date_stop' => '2026-10-18']]]);
            }

            return $this->http->response(['data' => [['impressions' => '1000', 'clicks' => '20', 'spend' => '10.00', 'account_currency' => 'USD', 'campaign_id' => '9', 'campaign_name' => 'Safari',
                'date_start' => '2026-09-19', 'date_stop' => '2026-10-18']]]);
        });

        $r = (new AdInsightsSync($this->graph(), fn () => ['meta_ad_account_id' => '5']))->run(1);

        $this->assertSame('act_5', $r['account']);
        $this->assertCount(1, $r['errors']);
        $this->assertStringContainsString('country', $r['errors'][0]);
        $this->assertSame(1, AdInsightRow::where('breakdown', 'country')->count(), 'old country rows survive a failed fetch');
        $this->assertSame(999, AdInsightRow::where('breakdown', 'country')->first()->impressions);
        $this->assertSame(1, AdInsightRow::where('breakdown', 'age_gender')->count());
        $this->assertSame('Safari', AdInsightRow::where('breakdown', 'campaign')->first()->label);

        // Re-running replaces, never duplicates.
        (new AdInsightsSync($this->graph(), fn () => ['meta_ad_account_id' => 'act_5']))->run(1);
        $this->assertSame(1, AdInsightRow::where('breakdown', 'age_gender')->count());
    }

    public function test_account_is_discovered_only_when_there_is_exactly_one(): void
    {
        $this->http->fake(['*' => $this->http->response(['data' => [['id' => 'act_1'], ['id' => 'act_2']]])]);
        $r = (new AdInsightsSync($this->graph(), fn () => []))->run(1);
        $this->assertNull($r['account']);
        $this->assertStringContainsString('Several ad accounts', $r['errors'][0]);
        $this->assertSame(0, AdInsightRow::count());
    }

    public function test_audience_insights_states_and_small_samples(): void
    {
        $this->assertSame('no_data', AdAudienceInsights::build([])['state']);

        $tiny = [['breakdown' => 'total', 'impressions' => 400, 'clicks' => 4, 'spend' => 1, 'currency' => 'USD']];
        $this->assertSame('too_early', AdAudienceInsights::build($tiny)['state']);

        $rows = [
            ['breakdown' => 'total', 'impressions' => 5000, 'clicks' => 100, 'spend' => 50, 'currency' => 'USD'],
            ['breakdown' => 'age_gender', 'dim1' => '25-34', 'dim2' => 'female', 'impressions' => 2000, 'clicks' => 80, 'spend' => 20],
            ['breakdown' => 'age_gender', 'dim1' => '35-44', 'dim2' => 'male', 'impressions' => 2000, 'clicks' => 20, 'spend' => 20],
            ['breakdown' => 'age_gender', 'dim1' => '18-24', 'dim2' => 'male', 'impressions' => 40, 'clicks' => 40, 'spend' => 1],   // 100% CTR on 40 impressions
        ];
        $r = AdAudienceInsights::build($rows);
        $this->assertSame('ok', $r['state']);
        $g = $r['groups']['age_gender'];
        $this->assertSame('25-34 · female', $g['best_ctr'], 'the 40-impression group must not win');
        $this->assertSame(4.0, $g['rows'][0]['ctr']);
        $this->assertSame(0.25, $g['rows'][0]['cpc']);

        $unknown = AdAudienceInsights::build([['breakdown' => 'total', 'impressions' => 5000, 'clicks' => null, 'spend' => null, 'currency' => null]]);
        $this->assertNull($unknown['total']['ctr']);
        $this->assertNull($unknown['total']['cpc']);
    }

    // ---- audience research --------------------------------------------------------------------------

    public function test_interest_search_and_reach_estimate(): void
    {
        $this->http->fake([
            '*search*' => $this->http->response(['data' => [['id' => '6003', 'name' => 'Safari', 'audience_size_lower_bound' => 1000000, 'audience_size_upper_bound' => 2000000, 'topic' => 'Travel'], ['id' => '6004', 'name' => 'Odd']]]),
            '*delivery_estimate*' => $this->http->response(['data' => [['estimate_ready' => true, 'estimate_mau_lower_bound' => 100000, 'estimate_mau_upper_bound' => 150000]]]),
        ]);
        $r = new AudienceResearch($this->graph());
        $i = $r->interests('safari');
        $this->assertSame(1000000, $i[0]['low']);
        $this->assertNull($i[1]['low'], 'missing size is unknown, not zero');

        $e = $r->reachEstimate('act_5', '6003', ['KE']);
        $this->assertSame([100000, 150000], [$e['low'], $e['high']]);
        $this->http->assertSent(fn (Request $q) => str_contains($q->url(), 'delivery_estimate') && str_contains(urldecode($q->url()), '"countries":["KE"]') && str_contains($q->url(), 'optimization_goal=REACH'));
    }

    // ---- comments ------------------------------------------------------------------------------------

    public function test_comments_are_saved_once_and_questions_flagged(): void
    {
        $this->http->fake(function (Request $r) {
            $u = $r->url();
            return match (true) {
                str_contains($u, 'me/accounts') => $this->http->response($this->pagesResponse()),
                str_contains($u, '77/posts') => $this->http->response(['data' => [['id' => '77_1']]]),
                str_contains($u, '77_1/comments') => $this->http->response(['data' => [
                    ['id' => 'c1', 'message' => 'How much for 3 days in the Mara?', 'created_time' => '2026-10-18T10:00:00+0000', 'like_count' => 2],
                    ['id' => 'c2', 'message' => 'Beautiful photos, love it', 'created_time' => '2026-10-18T11:00:00+0000'],
                    ['id' => 'c3', 'message' => '   '],
                ]]),
                str_contains($u, '88/media') => $this->http->response(['data' => [['id' => 'm1']]]),
                str_contains($u, 'm1/comments') => $this->http->response(['data' => [['id' => 'i1', 'text' => 'Is this available in December?', 'timestamp' => '2026-10-18T12:00:00+0000']]]),
                default => $this->http->response([], 404),
            };
        });
        $sync = new CommentSync($this->graph(), $this->assets(), fn () => ['meta_page_id' => '77']);

        $r = $sync->run(1);
        $this->assertSame(3, $r['saved']);
        $this->assertSame([], $r['errors']);
        $this->assertTrue((bool) AudienceComment::where('remote_id', 'c1')->first()->is_question);
        $this->assertFalse((bool) AudienceComment::where('remote_id', 'c2')->first()->is_question);
        $this->assertSame('positive', AudienceComment::where('remote_id', 'c2')->first()->sentiment);
        $this->assertNull(AudienceComment::where('remote_id', 'c2')->first()->likes, 'unknown likes stay null');

        $this->assertSame(0, $sync->run(1)['saved'], 'second run adds nothing');
        $this->assertSame(3, AudienceComment::count());
    }

    public function test_a_failing_network_does_not_stop_the_other_and_reports_why(): void
    {
        $this->http->fake(function (Request $r) {
            $u = $r->url();
            return match (true) {
                str_contains($u, 'me/accounts') => $this->http->response($this->pagesResponse()),
                str_contains($u, '77/posts') => $this->http->response(['error' => ['code' => 10, 'message' => 'Requires pages_read_user_content']], 403),
                str_contains($u, '88/media') => $this->http->response(['data' => [['id' => 'm1']]]),
                str_contains($u, 'm1/comments') => $this->http->response(['data' => [['id' => 'i1', 'text' => 'Nice']]]),
                default => $this->http->response([], 404),
            };
        });
        $r = (new CommentSync($this->graph(), $this->assets(), fn () => ['meta_page_id' => '77']))->run(1);
        $this->assertSame(1, $r['saved']);
        $this->assertStringContainsString('Facebook comments', $r['errors'][0]);
    }

    // ---- leads ---------------------------------------------------------------------------------------

    public function test_lead_form_fields_are_mapped_and_nothing_is_dropped(): void
    {
        $l = LeadSync::toLead(['id' => 'L1', 'created_time' => '2026-10-18T09:00:00+0000', 'campaign_name' => 'Safari Q4', 'field_data' => [
            ['name' => 'full_name', 'values' => ['Amina Otieno']], ['name' => 'email', 'values' => ['a@example.com']],
            ['name' => 'phone_number', 'values' => ['+254700000000']], ['name' => 'travel_month', 'values' => ['December']],
            ['name' => 'empty', 'values' => ['']],
        ]], 'Safari form');

        $this->assertSame(['Amina', 'Otieno'], [$l['first_name'], $l['last_name']]);
        $this->assertSame('a@example.com', $l['email']);
        $this->assertSame('+254700000000', $l['phone']);
        $this->assertSame('travel month: December', $l['message']);
        $this->assertSame('social', $l['source']);
        $this->assertSame('L1', $l['metadata']['meta_lead_id']);
        $this->assertSame('Safari Q4', $l['metadata']['meta_campaign']);
    }

    public function test_leads_are_created_once(): void
    {
        $this->http->fake(function (Request $r) {
            $u = $r->url();
            return match (true) {
                str_contains($u, 'me/accounts') => $this->http->response($this->pagesResponse()),
                str_contains($u, 'leadgen_forms') => $this->http->response(['data' => [['id' => 'F1', 'name' => 'Safari form']]]),
                str_contains($u, 'F1/leads') => $this->http->response(['data' => [
                    ['id' => 'L1', 'field_data' => [['name' => 'email', 'values' => ['a@example.com']]]],
                    ['id' => 'L2', 'field_data' => [['name' => 'email', 'values' => ['b@example.com']]]],
                ]]),
                default => $this->http->response([], 404),
            };
        });
        $sync = new LeadSync($this->graph(), $this->assets(), fn () => ['meta_page_id' => '77']);

        $this->assertSame(2, $sync->run(1)['created']);
        $this->assertSame(0, $sync->run(1)['created']);
        $this->assertSame(2, Lead::count());
        $this->assertSame('new', Lead::first()->status);
    }

    public function test_lead_sync_reports_missing_permission(): void
    {
        $this->http->fake(function (Request $r) {
            return str_contains($r->url(), 'me/accounts')
                ? $this->http->response($this->pagesResponse())
                : $this->http->response(['error' => ['code' => 200, 'message' => 'Requires leads_retrieval']], 403);
        });
        $r = (new LeadSync($this->graph(), $this->assets(), fn () => ['meta_page_id' => '77']))->run(1);
        $this->assertSame(0, $r['created']);
        $this->assertStringContainsString('leads_retrieval', $r['errors'][0]);
    }

    public function test_compact_view_for_the_agent_keeps_top_three_and_no_extras(): void
    {
        $rows = [['breakdown' => 'total', 'impressions' => 9000, 'clicks' => 90, 'spend' => 90, 'currency' => 'USD']];
        foreach ([['18-24', 'male'], ['25-34', 'female'], ['35-44', 'male'], ['45-54', 'female']] as $i => [$a, $g]) {
            $rows[] = ['breakdown' => 'age_gender', 'dim1' => $a, 'dim2' => $g, 'impressions' => 2000 - $i * 100, 'clicks' => 20 + $i, 'spend' => 10];
        }
        $c = AdAudienceInsights::compact(AdAudienceInsights::build($rows));
        $this->assertCount(3, $c['groups']['age_gender']['top']);
        $this->assertArrayNotHasKey('reach', $c['groups']['age_gender']['top'][0]);
        $this->assertSame('ok', $c['state']);
        $this->assertSame([], AdAudienceInsights::compact(AdAudienceInsights::build([]))['groups']);
    }

    public function test_page_is_found_directly_when_me_accounts_is_empty_for_a_system_user(): void
    {
        $this->http->fake(function (Request $r) {
            $u = $r->url();
            return match (true) {
                str_contains($u, 'me/accounts') => $this->http->response(['data' => []]),
                str_contains($u, '/77?') => $this->http->response(['id' => '77', 'name' => 'Vumbi', 'access_token' => 'page-token', 'instagram_business_account' => ['id' => '88', 'username' => 'vumbi']]),
                default => $this->http->response([], 404),
            };
        });
        $page = $this->assets()->pageFor(['meta_page_id' => '77']);
        $this->assertSame(['77', 'page-token', '88'], [$page['id'], $page['token'], $page['instagram_id']]);
    }

    public function test_page_without_a_page_token_is_reported_not_guessed(): void
    {
        $this->http->fake(function (Request $r) {
            return str_contains($r->url(), 'me/accounts')
                ? $this->http->response(['data' => []])
                : $this->http->response(['id' => '77', 'name' => 'Vumbi']);
        });
        $this->expectException(\App\Services\Meta\MetaApiException::class);
        $this->expectExceptionMessage('no Page access token');
        $this->assets()->pageFor(['meta_page_id' => '77']);
    }
}
