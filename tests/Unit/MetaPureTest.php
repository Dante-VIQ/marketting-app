<?php

namespace Tests\Unit;

use App\Services\Meta\MetaApiException;
use App\Services\Meta\MetaCapabilities as C;
use App\Services\Meta\MetaParsers as P;
use PHPUnit\Framework\TestCase;

class MetaPureTest extends TestCase
{
    /** The scopes visible in the Graph API Explorer screenshots. */
    private const CURRENT = ['pages_show_list', 'ads_management', 'ads_read', 'business_management', 'whatsapp_business_management', 'pages_read_engagement', 'whatsapp_business_messaging'];

    public function test_current_token_can_do_ads_and_read_posts_but_not_post_or_read_insights(): void
    {
        $this->assertTrue(C::has(self::CURRENT, 'ads_manage'));
        $this->assertTrue(C::has(self::CURRENT, 'page_posts'));
        $this->assertFalse(C::has(self::CURRENT, 'page_publish'));
        $this->assertFalse(C::has(self::CURRENT, 'page_insights'));
        $this->assertFalse(C::has(self::CURRENT, 'ig_publish'));
    }

    public function test_missing_scopes_lists_what_to_add_without_duplicates(): void
    {
        $missing = C::missingForBuilt(self::CURRENT);
        $this->assertContains('pages_manage_posts', $missing);
        $this->assertContains('read_insights', $missing);
        $this->assertContains('instagram_content_publish', $missing);
        $this->assertNotContains('ads_read', $missing);
        $this->assertSame(count($missing), count(array_unique($missing)));
        $this->assertNotContains('pages_read_user_content', $missing);   // only needed by a feature not built yet
    }

    public function test_instagram_publishing_needs_its_dependencies(): void
    {
        $this->assertFalse(C::has(['instagram_content_publish'], 'ig_publish'));
        $this->assertTrue(C::has(['instagram_content_publish', 'instagram_basic', 'pages_read_engagement', 'pages_show_list'], 'ig_publish'));
    }

    public function test_debug_token_parsing_and_never_expiring_token(): void
    {
        $t = P::debugToken(['data' => ['is_valid' => true, 'type' => 'SYSTEM_USER', 'expires_at' => 0, 'scopes' => ['ads_read'], 'app_id' => '1']]);
        $this->assertTrue($t['valid']);
        $this->assertTrue($t['never_expires']);
        $this->assertNull($t['expires_at']);

        $short = P::debugToken(['data' => ['is_valid' => true, 'expires_at' => 1893456000, 'scopes' => []]]);
        $this->assertSame('2030-01-01 00:00:00', $short['expires_at']);
        $this->assertFalse($short['never_expires']);
    }

    public function test_metric_reads_both_response_shapes_and_returns_null_when_absent(): void
    {
        $r = ['data' => [['name' => 'a', 'values' => [['value' => 12]]], ['name' => 'b', 'total_value' => ['value' => 7]]]];
        $this->assertSame(12, P::metric($r, 'a'));
        $this->assertSame(7, P::metric($r, 'b'));
        $this->assertNull(P::metric($r, 'missing'));
    }

    public function test_facebook_post_engagement_and_unknowns(): void
    {
        $post = ['reactions' => ['summary' => ['total_count' => 10]], 'comments' => ['summary' => ['total_count' => 2]], 'shares' => ['count' => 3]];
        $ins = ['data' => [['name' => 'post_media_view', 'values' => [['value' => 500]]]]];
        $r = P::facebookPost($post, $ins);
        $this->assertSame(500, $r['impressions']);
        $this->assertSame(15, $r['engagements']);
        $this->assertNull($r['reach']);        // metric not returned: unknown, not 0
        $this->assertNull($r['clicks']);
    }

    public function test_facebook_post_without_shares_key_means_zero_shares_only_when_reactions_were_read(): void
    {
        $this->assertSame(0, P::facebookPost(['reactions' => ['summary' => ['total_count' => 4]]], [])['shares']);
        $this->assertNull(P::facebookPost([], [])['shares']);
        $this->assertNull(P::facebookPost([], [])['engagements']);
    }

    public function test_instagram_uses_total_interactions_else_sums_parts(): void
    {
        $withTotal = P::instagramMedia([], ['data' => [['name' => 'total_interactions', 'values' => [['value' => 40]]], ['name' => 'views', 'values' => [['value' => 900]]]]]);
        $this->assertSame(40, $withTotal['engagements']);
        $this->assertSame(900, $withTotal['impressions']);

        $parts = P::instagramMedia(['like_count' => 5, 'comments_count' => 1], []);
        $this->assertSame(6, $parts['engagements']);
        $this->assertNull($parts['impressions']);
    }

    public function test_format_mapping(): void
    {
        $this->assertSame('short_video', P::instagramFormat('IMAGE', 'REELS'));
        $this->assertSame('carousel', P::instagramFormat('CAROUSEL_ALBUM'));
        $this->assertSame('image', P::facebookFormat('added_photos'));
        $this->assertSame('other', P::facebookFormat(null));
    }

    public function test_error_kinds(): void
    {
        $this->assertSame('token_expired', MetaApiException::fromResponse(400, ['error' => ['code' => 190, 'message' => 'x']])->kind());
        $this->assertSame('invalid_parameter', MetaApiException::fromResponse(400, ['error' => ['code' => 100, 'message' => 'x']])->kind());
        $this->assertSame('permission', MetaApiException::fromResponse(403, ['error' => ['code' => 200, 'message' => 'x']])->kind());
        $this->assertSame('rate_limit', MetaApiException::fromResponse(400, ['error' => ['code' => 4, 'message' => 'x']])->kind());
        $this->assertSame('other', MetaApiException::fromResponse(500, null)->kind());
    }
}
