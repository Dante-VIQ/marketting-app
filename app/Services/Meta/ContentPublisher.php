<?php

namespace App\Services\Meta;

use App\Models\Brand;
use App\Models\ContentIdea;
use Carbon\Carbon;
use DomainException;

/**
 * Auto-posting with safeguards:
 *  - off unless META_PUBLISHING_ENABLED is true;
 *  - only posts a human explicitly approved (approved_by);
 *  - a daily cap per brand;
 *  - a post is claimed before the API call and is never retried automatically, so a timeout cannot cause a double post.
 */
class ContentPublisher
{
    /** @param callable|null $brandConfig fn(int $brandId): array */
    public function __construct(private MetaAssets $assets, private MetaPublisher $publisher, private $brandConfig = null)
    {
        $this->brandConfig ??= fn (int $id) => Brand::find($id)?->config ?? [];
    }

    public static function enabled(): bool
    {
        return (bool) config('meta.publishing.enabled', false);
    }

    /** Human approval: validates and queues the post for its scheduled time. */
    public function approve(ContentIdea $idea, int $approverId, Carbon $scheduledAt, ?string $mediaUrl, ?string $linkUrl): void
    {
        if (!self::enabled()) {
            throw new DomainException('Auto-posting is switched off (META_PUBLISHING_ENABLED=false). You can still post by hand.');
        }
        if (!in_array($idea->platform, ['facebook', 'instagram'], true)) {
            throw new DomainException('Choose Facebook or Instagram as the platform first.');
        }
        if (!in_array($idea->status, ['idea', 'ready', 'failed'], true)) {
            throw new DomainException('Only ideas that are not yet posted or scheduled can be approved.');
        }
        if (trim((string) $idea->copy) === '') {
            throw new DomainException('Write the post text first.');
        }
        if ($idea->platform === 'instagram' && !self::publicHttps($mediaUrl)) {
            throw new DomainException('Instagram needs a public https image URL (JPEG).');
        }
        if ($mediaUrl && !self::publicHttps($mediaUrl)) {
            throw new DomainException('The image URL must be a public https link.');
        }
        if ($linkUrl && !preg_match('#^https?://#i', $linkUrl)) {
            throw new DomainException('The link must start with http:// or https://.');
        }
        if ($scheduledAt->lt(now()->subMinute())) {
            throw new DomainException('Pick a time in the future.');
        }

        $idea->update(['status' => 'scheduled', 'publish_mode' => 'auto', 'scheduled_at' => $scheduledAt, 'approved_by' => $approverId,
            'approved_at' => now(), 'media_url' => $mediaUrl ?: null, 'link_url' => $linkUrl ?: null, 'publish_error' => null]);
    }

    public function cancel(ContentIdea $idea): void
    {
        if ($idea->status === 'scheduled') {
            $idea->update(['status' => 'ready', 'publish_mode' => 'manual', 'scheduled_at' => null, 'approved_by' => null, 'approved_at' => null]);
        }
    }

    /** @return array{posted:int,failed:int,skipped:int} */
    public function publishDue(?int $brandId = null): array
    {
        $r = ['posted' => 0, 'failed' => 0, 'skipped' => 0];
        if (!self::enabled()) {
            return $r;
        }

        $due = ContentIdea::where('status', 'scheduled')->whereNotNull('approved_by')->where('scheduled_at', '<=', now())
            ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))->orderBy('scheduled_at')->limit(20)->get();

        foreach ($due as $idea) {
            if ($this->postedToday($idea->brand_id) >= (int) config('meta.publishing.max_per_day', 5)) {
                $r['skipped']++;
                continue;   // stays scheduled; goes out when the daily cap resets
            }
            // Claim it: only one runner can move scheduled -> publishing.
            if (ContentIdea::where('id', $idea->id)->where('status', 'scheduled')->update(['status' => 'publishing']) !== 1) {
                continue;
            }
            $this->publishOne($idea) ? $r['posted']++ : $r['failed']++;
        }

        return $r;
    }

    private function publishOne(ContentIdea $idea): bool
    {
        try {
            $page = $this->assets->pageFor(($this->brandConfig)($idea->brand_id));
            $res = $idea->platform === 'instagram'
                ? $this->publisher->instagram($page, (string) $idea->copy, (string) $idea->media_url)
                : $this->publisher->facebook($page, (string) $idea->copy, $idea->link_url, $idea->media_url);
        } catch (MetaApiException $e) {
            $idea->update(['status' => 'failed', 'publish_error' => trim($e->getMessage() . ' ' . $e->hint())]);

            return false;
        } catch (\Throwable $e) {
            // Outcome unknown (e.g. timeout after sending): never retry blindly.
            report($e);
            $idea->update(['status' => 'failed', 'publish_error' => 'Could not confirm whether this was posted. Check your Page before retrying. (' . $e->getMessage() . ')']);

            return false;
        }

        $idea->update(['status' => 'posted', 'posted_at' => now(), 'remote_post_id' => $res['id'], 'post_url' => $res['url'], 'publish_error' => null]);

        return true;
    }

    private function postedToday(int $brandId): int
    {
        return ContentIdea::where('brand_id', $brandId)->where('publish_mode', 'auto')->where('status', 'posted')
            ->where('posted_at', '>=', now()->startOfDay())->count();
    }

    public static function publicHttps(?string $url): bool
    {
        if (!$url || !preg_match('#^https://([^/:?\#]+)#i', $url, $m)) {
            return false;
        }
        $host = strtolower($m[1]);

        return $host !== 'localhost' && !preg_match('/^(127\.|10\.|192\.168\.|169\.254\.|172\.(1[6-9]|2\d|3[01])\.)/', $host) && str_contains($host, '.');
    }
}
