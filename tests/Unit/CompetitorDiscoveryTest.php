<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Competitor;
use App\Models\CompetitorSuggestion;
use App\Services\AI\AiGatewayService;
use App\Services\Competitors\CompetitorScanner;
use App\Services\Competitors\Discovery\BrandProfile;
use App\Services\Competitors\Discovery\CandidateFinder;
use App\Services\Competitors\Discovery\CompetitorDiscovery;
use App\Services\Competitors\Discovery\Relevance;
use App\Services\Competitors\Discovery\SearchProvider;
use App\Services\Competitors\Discovery\TavilySearch;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Http\Client\Factory;
use Tests\Unit\Support\MetaTestCase;

class CompetitorDiscoveryTest extends MetaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Container::getInstance()->instance('cache', new Repository(new ArrayStore()));
        $s = DB::schema();
        $s->create('knowledge_base', function ($t) { $t->id(); $t->unsignedBigInteger('brand_id'); $t->string('key'); $t->string('category')->default('general'); $t->longText('content'); $t->text('metadata')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); });
        $s->create('page_snapshots', function ($t) { $t->id(); $t->unsignedBigInteger('brand_id'); $t->string('url')->nullable(); $t->string('page_type')->nullable(); $t->string('title')->nullable(); $t->text('headings')->nullable(); $t->text('topics_covered')->nullable(); $t->text('content')->nullable(); $t->string('status')->default('completed'); $t->timestamp('scraped_at')->nullable(); $t->timestamps(); });
        $s->create('keyword_rankings', function ($t) { $t->id(); $t->unsignedBigInteger('brand_id'); $t->string('keyword'); $t->string('page_url')->nullable(); $t->integer('position')->nullable(); $t->integer('previous_position')->nullable(); $t->integer('search_volume')->nullable(); $t->integer('difficulty')->nullable(); $t->text('metadata')->nullable(); $t->date('tracked_date')->nullable(); $t->timestamps(); });
        $s->create('personas', function ($t) { $t->id(); $t->unsignedBigInteger('brand_id'); $t->string('name'); $t->boolean('is_active')->default(true); $t->timestamps(); });
        $s->create('competitors', function ($t) { $t->id(); $t->unsignedBigInteger('brand_id'); $t->string('name')->nullable(); $t->string('domain'); $t->boolean('is_active')->default(true); $t->text('notes')->nullable(); $t->timestamps(); });
        $s->create('competitor_suggestions', function ($t) {
            $t->id(); $t->unsignedBigInteger('brand_id'); $t->string('domain'); $t->string('name')->nullable(); $t->text('sources'); $t->string('classification', 20);
            $t->unsignedTinyInteger('score')->nullable(); $t->text('shared_terms')->nullable(); $t->unsignedTinyInteger('query_hits')->default(0); $t->text('reason')->nullable();
            $t->string('homepage_title')->nullable(); $t->string('status', 12)->default('suggested'); $t->timestamps();
        });
        Container::getInstance()['config']->set('competitors', ['big_platforms' => ['tripadvisor.com'], 'max_candidates_to_check' => 20, 'max_new_suggestions' => 12, 'max_big_platforms' => 5, 'max_search_queries' => 6]);
    }

    private function brand(bool $withFacts = true): Brand
    {
        $b = new Brand();
        $b->forceFill(['id' => 1, 'name' => 'Vumbi Ventures', 'website_url' => 'https://vumbiventures.com'])->exists = true;
        if ($withFacts) {
            foreach (['business_description' => 'Safari travel guides and trip planning for East Africa: Kenya, Tanzania and Uganda national parks, wildlife and culture.',
                'content_pillars' => 'East Africa destination guides, Maasai Mara wildlife, Kenya culture and history',
                'competitors' => '1. SafariBookings.com - direct competitor'] as $k => $v) {
                DB::table('knowledge_base')->insert(['brand_id' => 1, 'key' => $k, 'content' => $v, 'is_active' => 1]);
            }
        }

        return $b;
    }

    private function sites(Factory $http, array $extra = []): void
    {
        $page = fn ($title, $body) => $http->response("<html><head><title>{$title}</title></head><body><h1>{$title}</h1><p>{$body}</p></body></html>");
        $http->fake($extra + [
            'https://rival-safaris.com/' => $page('Rival Safaris', 'Book a Kenya safari. Maasai Mara wildlife tours, Tanzania and Uganda national parks, East Africa trip planning. Travel guides and blog.'),
            'https://safaribookings.com/' => $page('SafariBookings', 'Compare tours and prices.'),
            'https://newsite.com/' => $page('Newsite', 'Kenya wildlife guides and Tanzania national parks stories, travel blog for East Africa.'),
            'https://unrelated.com/' => $page('Unrelated', 'Accounting software for dentists.'),
            '*' => $http->response('', 404),   // dead.com and anything else
        ]);
    }

    private function discovery(Factory $http, ?array $aiJson, ?SearchProvider $search): CompetitorDiscovery
    {
        $ai = $this->createMock(AiGatewayService::class);
        $ai->method('generate')->willReturn($aiJson === null ? ['success' => false] : ['success' => true, 'content' => json_encode($aiJson)]);
        $scanner = new CompetitorScanner($http, fn () => ['93.184.216.34']);

        return new CompetitorDiscovery(new CandidateFinder($ai, $search), $scanner);
    }

    private function search(array|string $byQuery): SearchProvider
    {
        return new class($byQuery) implements SearchProvider {
            public function __construct(private array|string $by) {}
            public function configured(): bool { return true; }
            public function search(string $query, int $max = 10): array
            {
                if ($this->by === 'fail') { throw new \RuntimeException('Tavily search limit reached for now.'); }
                return array_map(fn ($u) => ['title' => 't', 'url' => $u, 'snippet' => 'Kenya safari wildlife'], $this->by[$query] ?? []);
            }
        };
    }

    // ---- pure logic ----

    public function test_terms_overlap_and_classification(): void
    {
        $terms = Relevance::terms(['Safari safari safari Kenya Kenya wildlife', 'the and platform'], ['vumbi']);
        $this->assertSame(['safari', 'kenya', 'wildlife'], $terms);
        $ov = Relevance::overlap($terms, 'Wildlife tours across Kenya');
        $this->assertSame(67, $ov['score']);
        $this->assertSame(['kenya', 'wildlife'], $ov['shared']);
        $this->assertSame(0, Relevance::overlap([], 'x')['score']);
        $this->assertSame('both', Relevance::classify('Book a safari. Read our travel guides', false));
        $this->assertSame('bookings', Relevance::classify('Book now, tours and packages', false));
        $this->assertSame('content', Relevance::classify('Welcome', true));
        $this->assertSame('unclear', Relevance::classify('Welcome', false));
        $this->assertTrue(Relevance::isBigPlatform('uk.tripadvisor.com', ['tripadvisor.com']));
        $this->assertFalse(Relevance::isBigPlatform('nottripadvisor.com', ['tripadvisor.com']));
        $this->assertContains('safaribookings.com', Relevance::domainsIn('1. SafariBookings.com - direct, see www.tripadvisor.com and file.php'));
    }

    public function test_parse_ai_keeps_real_looking_domains_and_drops_junk(): void
    {
        $r = CandidateFinder::parseAi('```json {"competitors":[{"domain":"https://www.Rival.com/x","name":"Rival","why":"same niche"},{"domain":"not a domain"},{"domain":"own.com"},{"domain":"rival.com"}],"queries":["best time safari kenya",""]} ```', 'own.com');
        $this->assertSame(['rival.com'], array_column($r['competitors'], 'domain'));
        $this->assertSame(['best time safari kenya'], $r['queries']);
        $this->assertNull(CandidateFinder::parseAi('nope'));
    }

    public function test_tavily_reads_results_and_explains_failures(): void
    {
        $http = new Factory();
        $http->fake(['api.tavily.com/*' => $http->response(['results' => [['title' => 'A', 'url' => 'https://a.com/x', 'content' => 'snip', 'score' => 0.9]]])]);
        $this->assertSame('https://a.com/x', (new TavilySearch($http, 'tvly-k'))->search('q')[0]['url']);
        $http->assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer tvly-k') && $r['search_depth'] === 'basic');
        $this->assertFalse((new TavilySearch($http, ''))->configured());
        $this->assertSame([], (new TavilySearch($http, null))->search('q'));

        $bad = new Factory();
        $bad->fake(['*' => $bad->response('', 401)]);
        $this->expectExceptionMessage('rejected the API key');
        (new TavilySearch($bad, 'tvly-k'))->search('q');
    }

    // ---- full run ----

    public function test_run_finds_checks_and_explains_candidates(): void
    {
        $http = new Factory();
        $this->sites($http);
        $ai = ['competitors' => [['domain' => 'rival-safaris.com', 'name' => 'Rival Safaris', 'why' => 'sells East Africa safaris'], ['domain' => 'dead.com'],
            ['domain' => 'unrelated.com'], ['domain' => 'tripadvisor.com'], ['domain' => 'vumbiventures.com']], 'queries' => ['kenya safari planning', 'east africa wildlife guide']];
        $search = $this->search(['kenya safari planning' => ['https://rival-safaris.com/a', 'https://newsite.com/b'], 'east africa wildlife guide' => ['https://rival-safaris.com/c']]);

        $r = $this->discovery($http, $ai, $search)->run($this->brand());

        $rows = CompetitorSuggestion::all()->keyBy('domain');
        $this->assertSame(['rival-safaris.com', 'newsite.com', 'safaribookings.com', 'tripadvisor.com'], $rows->keys()->sortBy(fn ($d) => ['rival-safaris.com' => 0, 'newsite.com' => 1, 'safaribookings.com' => 2, 'tripadvisor.com' => 3][$d])->values()->all());
        $this->assertSame(4, $r['found']);
        $this->assertTrue($r['used_search']);

        $rival = $rows['rival-safaris.com'];
        $this->assertEqualsCanonicalizing(['ai', 'search'], $rival->sources);
        $this->assertSame(2, $rival->query_hits);
        $this->assertSame('both', $rival->classification);
        $this->assertGreaterThan(30, $rival->score);
        $this->assertContains('kenya', $rival->shared_terms);
        $this->assertStringContainsString('Appeared in 2 of the searches', $rival->reason);
        $this->assertStringContainsString('unverified', $rival->reason);   // AI judgement is labelled as such

        $this->assertSame('big_platform', $rows['tripadvisor.com']->classification);
        $this->assertNull($rows['tripadvisor.com']->score);
        $this->assertSame(['your_notes'], $rows['safaribookings.com']->sources);   // from the brand's own notes, kept despite thin overlap
        $this->assertArrayNotHasKey('dead.com', $rows->all());        // unreachable: left out, not scored zero
        $this->assertArrayNotHasKey('unrelated.com', $rows->all());   // reachable but shares none of the brand's words
        $this->assertArrayNotHasKey('vumbiventures.com', $rows->all());
        $this->assertStringContainsString('could not be reached', implode(' ', $r['notes']));
    }

    public function test_decisions_are_remembered_and_tracked_sites_are_skipped(): void
    {
        $http = new Factory();
        $this->sites($http);
        $brand = $this->brand();
        CompetitorSuggestion::create(['brand_id' => 1, 'domain' => 'rival-safaris.com', 'sources' => ['ai'], 'classification' => 'both', 'status' => 'dismissed']);
        CompetitorSuggestion::create(['brand_id' => 1, 'domain' => 'newsite.com', 'sources' => ['ai'], 'classification' => 'content', 'score' => 99, 'status' => 'accepted']);
        Competitor::create(['brand_id' => 1, 'domain' => 'safaribookings.com', 'name' => 'SB']);
        $ai = ['competitors' => [['domain' => 'rival-safaris.com'], ['domain' => 'newsite.com']], 'queries' => []];

        $r = $this->discovery($http, $ai, null)->run($brand);

        $this->assertSame(0, $r['found']);
        $this->assertSame('dismissed', CompetitorSuggestion::where('domain', 'rival-safaris.com')->first()->status);
        $this->assertSame(99, (int) CompetitorSuggestion::where('domain', 'newsite.com')->first()->score);   // not overwritten
        $this->assertSame(0, CompetitorSuggestion::where('domain', 'safaribookings.com')->count());
    }

    public function test_works_without_a_search_key_and_says_so(): void
    {
        $http = new Factory();
        $this->sites($http);
        $r = $this->discovery($http, ['competitors' => [['domain' => 'newsite.com']], 'queries' => []], null)->run($this->brand());

        $this->assertFalse($r['used_search']);
        $this->assertStringContainsString('TAVILY_API_KEY', implode(' ', $r['notes']));
        $this->assertSame(1, CompetitorSuggestion::where('domain', 'newsite.com')->count());
    }

    public function test_search_failure_and_ai_failure_do_not_stop_the_other_source(): void
    {
        $http = new Factory();
        $this->sites($http);
        $r = $this->discovery($http, ['competitors' => [['domain' => 'newsite.com']], 'queries' => ['q']], $this->search('fail'))->run($this->brand());
        $this->assertStringContainsString('limit reached', implode(' ', $r['notes']));
        $this->assertSame(1, CompetitorSuggestion::where('domain', 'newsite.com')->count());

        CompetitorSuggestion::query()->delete();
        $r = $this->discovery($http, null, $this->search(['x' => []]))->run($this->brand());   // AI down: still uses the brand's own notes
        $this->assertStringContainsString('AI', implode(' ', $r['notes']));
        $this->assertSame(1, CompetitorSuggestion::where('domain', 'safaribookings.com')->count());
    }

    public function test_refuses_to_guess_when_the_brand_is_unknown(): void
    {
        $http = new Factory();
        $this->expectException(\DomainException::class);
        $this->discovery($http, ['competitors' => [['domain' => 'newsite.com']], 'queries' => []], null)->run($this->brand(false));
    }

    public function test_brand_profile_collects_real_data_only(): void
    {
        $p = BrandProfile::build($this->brand());
        $this->assertSame('vumbiventures.com', $p['own_domain']);
        $this->assertSame(['safaribookings.com'], $p['noted_competitors']);
        $this->assertContains('kenya', $p['terms']);
        $this->assertNotContains('vumbi', $p['terms']);
        $this->assertFalse(BrandProfile::isThin($p));
        DB::table('knowledge_base')->delete();
        $this->assertTrue(BrandProfile::isThin(BrandProfile::build($this->brand(false))));
    }
}
