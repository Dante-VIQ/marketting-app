<?php

namespace Tests\Unit;

use App\Services\Listening\MentionAnalyzer as M;
use PHPUnit\Framework\TestCase;

class MentionAnalyzerTest extends TestCase
{
    public function test_matches_whole_words_only(): void
    {
        $this->assertTrue(M::matches('Acme Safaris', 'Review: acme safaris was great'));
        $this->assertFalse(M::matches('Acme', 'The acmetic company'));
        $this->assertFalse(M::matches('Acme', ''));
        $this->assertFalse(M::matches('', 'anything'));
        $this->assertTrue(M::matches('vumbi', 'Vumbi, the travel site'));
    }

    public function test_sentiment_with_negation_and_default_neutral(): void
    {
        $this->assertSame('positive', M::sentiment('Great service, highly recommended'));
        $this->assertSame('negative', M::sentiment('Total scam, avoid'));
        $this->assertSame('negative', M::sentiment('not good at all'));
        $this->assertSame('positive', M::sentiment('not bad actually'));
        $this->assertSame('neutral', M::sentiment('Company opens new office in Nairobi'));
        $this->assertNull(M::sentiment('   '));
    }

    public function test_url_hash_ignores_scheme_trailing_slash_and_tracking_query(): void
    {
        $this->assertSame(M::urlHash('https://a.com/post/'), M::urlHash('http://a.com/post?utm_source=x'));
        $this->assertNotSame(M::urlHash('https://a.com/post'), M::urlHash('https://a.com/other'));
    }

    public function test_is_conversation(): void
    {
        $this->assertTrue(M::isConversation('Anyone tried Acme?', 0));
        $this->assertTrue(M::isConversation('Acme review', 12));
        $this->assertFalse(M::isConversation('Acme review', 1));
        $this->assertFalse(M::isConversation('Acme review', null));
    }

    public function test_google_news_splits_publisher(): void
    {
        $r = M::fromGoogleNews([['title' => 'Kenya tourism up 12% - The Standard', 'url' => 'https://n.com/1', 'date' => '2026-10-15T00:00:00+00:00'],
            ['title' => 'No link', 'url' => null, 'date' => null]]);
        $this->assertCount(1, $r);
        $this->assertSame('Kenya tourism up 12%', $r[0]['title']);
        $this->assertSame('The Standard', $r[0]['publisher']);
    }

    public function test_reddit_and_hn_parsing_and_garbage(): void
    {
        $r = M::fromReddit(['data' => ['children' => [['data' => ['title' => 'Acme thoughts?', 'permalink' => '/r/travel/comments/x/acme/', 'subreddit' => 'travel',
            'author' => 'bob', 'score' => 10, 'num_comments' => 7, 'created_utc' => 1792000000, 'selftext' => '']]]]]);
        $this->assertSame('https://www.reddit.com/r/travel/comments/x/acme/', $r[0]['url']);
        $this->assertSame('r/travel', $r[0]['publisher']);
        $this->assertNull($r[0]['snippet']);
        $this->assertSame(7, $r[0]['comments']);

        $h = M::fromHackerNews(['hits' => [['title' => 'Show HN: Acme', 'objectID' => '42', 'points' => 5, 'num_comments' => 2, 'created_at_i' => 1792000000, 'author' => 'a'], ['objectID' => '9']]]);
        $this->assertCount(1, $h);
        $this->assertSame('https://news.ycombinator.com/item?id=42', $h[0]['url']);

        $this->assertSame([], M::fromReddit([]));
        $this->assertSame([], M::fromHackerNews(['nope' => 1]));
    }
}
