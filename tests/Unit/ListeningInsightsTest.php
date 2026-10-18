<?php

namespace Tests\Unit;

use App\Models\KeywordRanking;
use App\Models\ListeningQuery;
use App\Models\Mention;
use App\Services\Listening\ListeningInsights;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/** Real Eloquent queries against in-memory SQLite, without booting Laravel. */
class ListeningInsightsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite not available');
        }
        $c = new Container();
        $c->instance('date', new DateFactory());
        Container::setInstance($c);
        Facade::setFacadeApplication($c);

        $db = new DB();
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->setAsGlobal();
        $db->bootEloquent();

        $s = DB::schema();
        $s->create('listening_queries', function ($t) {
            $t->id(); $t->unsignedBigInteger('brand_id'); $t->string('term'); $t->string('kind');
            $t->boolean('is_active')->default(true); $t->timestamp('last_checked_at')->nullable(); $t->json('source_status')->nullable(); $t->timestamps();
        });
        $s->create('mentions', function ($t) {
            $t->id(); $t->unsignedBigInteger('brand_id'); $t->unsignedBigInteger('listening_query_id'); $t->string('source'); $t->string('url_hash');
            $t->string('title'); $t->string('url'); $t->text('snippet')->nullable(); $t->string('author')->nullable(); $t->string('publisher')->nullable();
            $t->timestamp('published_at')->nullable(); $t->integer('score')->nullable(); $t->integer('comments')->nullable(); $t->string('sentiment')->nullable();
            $t->boolean('is_dismissed')->default(false); $t->timestamps();
        });
        $s->create('keyword_rankings', function ($t) {
            $t->id(); $t->unsignedBigInteger('brand_id'); $t->string('keyword'); $t->string('page_url')->nullable(); $t->integer('position')->nullable();
            $t->integer('previous_position')->nullable(); $t->integer('search_volume')->nullable(); $t->string('difficulty')->nullable(); $t->json('metadata')->nullable();
            $t->date('tracked_date')->nullable(); $t->timestamps();
        });
    }

    private function q(string $term, string $kind, bool $checked = true): ListeningQuery
    {
        return ListeningQuery::create(['brand_id' => 1, 'term' => $term, 'kind' => $kind, 'last_checked_at' => $checked ? now() : null,
            'source_status' => $checked ? ['google_news' => 'ok', 'reddit' => 'blocked', 'hackernews' => 'ok'] : null]);
    }

    private function m(ListeningQuery $q, string $title, int $daysAgo, array $extra = []): Mention
    {
        return Mention::create($extra + ['brand_id' => 1, 'listening_query_id' => $q->id, 'source' => 'hackernews', 'url_hash' => sha1($title . $daysAgo),
            'title' => $title, 'url' => 'https://x.com/' . md5($title), 'published_at' => now()->subDays($daysAgo)]);
    }

    public function test_no_queries(): void
    {
        $s = (new ListeningInsights())->summary(1);
        $this->assertSame('no_queries', $s['state']);
    }

    public function test_queries_never_checked(): void
    {
        $this->q('Acme', 'brand', false);
        $s = (new ListeningInsights())->summary(1);
        $this->assertSame('never_checked', $s['state']);
        $this->assertNotEmpty($s['suggestions']['tips']);
    }

    public function test_checked_with_zero_mentions_is_a_starting_point_not_a_drop(): void
    {
        $this->q('Acme', 'brand');
        KeywordRanking::create(['brand_id' => 1, 'keyword' => 'kenya safari', 'search_volume' => 900, 'tracked_date' => now()->toDateString()]);
        KeywordRanking::create(['brand_id' => 1, 'keyword' => 'acme', 'search_volume' => 50, 'tracked_date' => now()->toDateString()]);

        $s = (new ListeningInsights())->summary(1);
        $this->assertSame('no_mentions_yet', $s['state']);
        $this->assertSame(0, $s['brand']['last_7d']);
        $this->assertNull($s['brand']['change_pct']);                         // no baseline, no trend
        $this->assertSame(['kenya safari'], $s['suggestions']['topics']);     // already-tracked 'acme' is not re-suggested
        $this->assertSame('blocked', $s['sources']['reddit']);                // a blocked source stays visible
        $this->assertSame(date('Y-m-d'), $s['checked_on']);
    }

    public function test_active_week_over_week_and_sentiment(): void
    {
        $brand = $this->q('Acme', 'brand');
        $this->m($brand, 'a', 1, ['sentiment' => 'positive']);
        $this->m($brand, 'b', 2, ['sentiment' => 'negative']);
        $this->m($brand, 'c', 3, ['sentiment' => 'positive']);
        $this->m($brand, 'd', 10, ['sentiment' => 'neutral']);

        $s = (new ListeningInsights())->summary(1);
        $this->assertSame('active', $s['state']);
        $this->assertSame(3, $s['brand']['last_7d']);
        $this->assertSame(1, $s['brand']['previous_7d']);
        $this->assertSame(200.0, $s['brand']['change_pct']);
        $this->assertSame(['positive' => 2, 'negative' => 1, 'neutral' => 1], $s['brand']['sentiment_30d']);
    }

    public function test_competitor_only_mentions_and_conversations(): void
    {
        $this->q('Acme', 'brand');
        $rival = $this->q('Rival', 'competitor');
        $topic = $this->q('kenya safari', 'topic');
        $this->m($rival, 'Rival launches tours', 2);
        $this->m($topic, 'Which safari operator is best?', 1, ['comments' => 2]);
        $this->m($topic, 'Safari packing list', 1, ['comments' => 1]);
        $this->m($topic, 'Dismissed thread?', 1, ['is_dismissed' => true]);

        $s = (new ListeningInsights())->summary(1);
        $this->assertSame('active', $s['state']);
        $this->assertSame(0, $s['brand']['total_30d']);
        $this->assertNotEmpty($s['suggestions']);                              // still guides: no brand mentions
        $this->assertSame('Rival', $s['share_of_voice'][0]['term']);
        $this->assertSame(['Which safari operator is best?'], array_column($s['conversations'], 'title'));
    }

    public function test_other_brands_data_is_never_mixed_in(): void
    {
        $this->q('Acme', 'brand');
        $other = ListeningQuery::create(['brand_id' => 2, 'term' => 'Other', 'kind' => 'brand', 'last_checked_at' => now()]);
        Mention::create(['brand_id' => 2, 'listening_query_id' => $other->id, 'source' => 'reddit', 'url_hash' => 'z', 'title' => 'x', 'url' => 'https://x.com', 'published_at' => now()]);
        $this->assertSame('no_mentions_yet', (new ListeningInsights())->summary(1)['state']);
    }
}
