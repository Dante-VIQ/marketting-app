<?php

namespace App\Services\Meta;

/** Pure mapping from Graph API responses to our numbers. Missing = null, never 0. */
class MetaParsers
{
    public static function debugToken(array $resp): array
    {
        $d = $resp['data'] ?? [];
        $exp = $d['expires_at'] ?? null;

        return [
            'valid' => (bool) ($d['is_valid'] ?? false),
            'type' => $d['type'] ?? null,                                   // USER | PAGE | SYSTEM_USER ...
            'app_id' => $d['app_id'] ?? null,
            'scopes' => array_values($d['scopes'] ?? []),
            'expires_at' => ($exp === null || (int) $exp === 0) ? null : date('Y-m-d H:i:s', (int) $exp),   // null = does not expire
            'never_expires' => $exp !== null && (int) $exp === 0,
            'data_access_expires_at' => !empty($d['data_access_expires_at']) ? date('Y-m-d', (int) $d['data_access_expires_at']) : null,
            'error' => $d['error']['message'] ?? null,
        ];
    }

    /** value of a metric in either {values:[{value}]} or {total_value:{value}} shape; null when absent. */
    public static function metric(array $insightsResp, string $name): ?int
    {
        foreach ($insightsResp['data'] ?? [] as $m) {
            if (($m['name'] ?? null) !== $name) {
                continue;
            }
            $v = $m['values'][0]['value'] ?? $m['total_value']['value'] ?? null;

            return is_numeric($v) ? (int) $v : null;
        }

        return null;
    }

    /** @return array{impressions:?int,reach:?int,clicks:?int,engagements:?int,shares:?int} impressions = Meta "media views" */
    public static function facebookPost(array $post, array $insights): array
    {
        $reactions = $post['reactions']['summary']['total_count'] ?? null;
        $comments = $post['comments']['summary']['total_count'] ?? null;
        $shares = $post['shares']['count'] ?? (array_key_exists('reactions', $post) ? 0 : null);   // Meta omits `shares` when there are none
        $parts = array_filter([$reactions, $comments, $shares], fn ($x) => $x !== null);

        return [
            'impressions' => self::metric($insights, 'post_media_view'),
            'reach' => self::metric($insights, 'post_total_media_view_unique'),
            'clicks' => self::metric($insights, 'post_clicks'),
            'engagements' => $parts ? (int) array_sum($parts) : null,
            'shares' => $shares !== null ? (int) $shares : null,
        ];
    }

    public static function instagramMedia(array $media, array $insights): array
    {
        $likes = self::metric($insights, 'likes') ?? $media['like_count'] ?? null;
        $comments = self::metric($insights, 'comments') ?? $media['comments_count'] ?? null;
        $shares = self::metric($insights, 'shares');
        $saved = self::metric($insights, 'saved');
        $total = self::metric($insights, 'total_interactions');
        $parts = array_filter([$likes, $comments, $shares, $saved], fn ($x) => $x !== null);

        return [
            'impressions' => self::metric($insights, 'views'),
            'reach' => self::metric($insights, 'reach'),
            'clicks' => null,
            'engagements' => $total ?? ($parts ? (int) array_sum($parts) : null),
            'shares' => $shares,
        ];
    }

    public static function facebookFormat(?string $statusType): string
    {
        return match ($statusType) {
            'added_photos' => 'image', 'added_video' => 'short_video',
            'mobile_status_update', 'wall_post', 'published_story' => 'text',
            default => 'other',
        };
    }

    public static function instagramFormat(?string $mediaType, ?string $product = null): string
    {
        if ($product === 'REELS') {
            return 'short_video';
        }

        return match ($mediaType) { 'IMAGE' => 'image', 'VIDEO' => 'short_video', 'CAROUSEL_ALBUM' => 'carousel', default => 'other' };
    }
}
