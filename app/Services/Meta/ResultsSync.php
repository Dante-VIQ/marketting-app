<?php

namespace App\Services\Meta;

use App\Models\Brand;
use App\Models\ContentIdea;

/**
 * Pulls what Meta knows into the Content Plan: imports recent posts (even ones you posted by hand,
 * so performance has history from day one) and refreshes results for posts we know the id of.
 */
class ResultsSync
{
    private const IMPORT_LIMIT = 25;
    private const UPDATE_LIMIT = 40;

    /** @param callable|null $brandConfig fn(int $brandId): array */
    public function __construct(private GraphClient $graph, private MetaAssets $assets, private MetaInsights $insights, private $brandConfig = null)
    {
        $this->brandConfig ??= fn (int $id) => Brand::find($id)?->config ?? [];
    }

    /** @return array{imported:int,updated:int,errors:string[]} */
    public function run(int $brandId): array
    {
        $out = ['imported' => 0, 'updated' => 0, 'errors' => []];
        try {
            $page = $this->assets->pageFor(($this->brandConfig)($brandId));
        } catch (MetaApiException $e) {
            $out['errors'][] = $e->getMessage() . ' ' . $e->hint();

            return $out;
        }
        $tok = $page['token'] ?? null;
        if (!$tok) {
            $out['errors'][] = 'No Page access token for ' . ($page['name'] ?? 'the Page') . '.';

            return $out;
        }

        // Each step returns a count. (Arrow functions capture $out by value, so counts are added out here.)
        $steps = [
            'Facebook posts' => ['imported', fn () => $this->importFacebook($brandId, $page, $tok)],
            'Instagram posts' => ['imported', fn () => $this->importInstagram($brandId, $page, $tok)],
            'Results' => ['updated', fn () => $this->refresh($brandId, $tok)],
        ];
        foreach ($steps as $label => [$counter, $step]) {
            try {
                $out[$counter] += $step();
            } catch (MetaApiException $e) {
                $out['errors'][] = "{$label}: {$e->getMessage()} {$e->hint()}";   // one failing network does not stop the others
            }
        }

        return $out;
    }

    private function importFacebook(int $brandId, array $page, string $tok): int
    {
        $posts = $this->graph->get("{$page['id']}/posts", [
            'fields' => 'id,message,created_time,permalink_url,status_type', 'limit' => self::IMPORT_LIMIT], $tok)['data'] ?? [];

        return $this->importRows($brandId, 'facebook', $posts, fn ($p) => [
            'id' => $p['id'], 'text' => $p['message'] ?? '', 'at' => $p['created_time'] ?? null,
            'url' => $p['permalink_url'] ?? null, 'format' => MetaParsers::facebookFormat($p['status_type'] ?? null)]);
    }

    private function importInstagram(int $brandId, array $page, string $tok): int
    {
        if (empty($page['instagram_id'])) {
            return 0;
        }
        $media = $this->graph->get("{$page['instagram_id']}/media", [
            'fields' => 'id,caption,media_type,media_product_type,permalink,timestamp', 'limit' => self::IMPORT_LIMIT], $tok)['data'] ?? [];

        return $this->importRows($brandId, 'instagram', $media, fn ($m) => [
            'id' => $m['id'], 'text' => $m['caption'] ?? '', 'at' => $m['timestamp'] ?? null,
            'url' => $m['permalink'] ?? null, 'format' => MetaParsers::instagramFormat($m['media_type'] ?? null, $m['media_product_type'] ?? null)]);
    }

    private function importRows(int $brandId, string $platform, array $rows, callable $map): int
    {
        $n = 0;
        foreach ($rows as $row) {
            $r = $map($row);
            if (ContentIdea::where('brand_id', $brandId)->where('remote_post_id', $r['id'])->exists()) {
                continue;
            }
            $title = trim(preg_replace('/\s+/', ' ', $r['text'])) ?: '(post without text)';
            ContentIdea::create(['brand_id' => $brandId, 'title' => mb_substr($title, 0, 120), 'platform' => $platform, 'format' => $r['format'],
                'source' => 'meta_import', 'copy' => $r['text'] ?: null, 'status' => 'posted', 'posted_at' => $r['at'],
                'post_url' => $r['url'], 'remote_post_id' => $r['id']]);
            $n++;
        }

        return $n;
    }

    private function refresh(int $brandId, string $tok): int
    {
        $ideas = ContentIdea::where('brand_id', $brandId)->where('status', 'posted')->whereNotNull('remote_post_id')
            ->whereIn('platform', ['facebook', 'instagram'])->where('posted_at', '>=', now()->subDays(60))
            ->orderByRaw('measured_at IS NOT NULL, measured_at')->limit(self::UPDATE_LIMIT)->get();

        $n = 0;
        foreach ($ideas as $idea) {
            $res = $idea->platform === 'instagram'
                ? $this->insights->instagramMedia($idea->remote_post_id, $tok)
                : $this->insights->facebookPost($idea->remote_post_id, $tok);
            // Only overwrite with numbers Meta actually returned.
            $vals = array_filter(array_intersect_key($res, array_flip(['impressions', 'reach', 'clicks', 'engagements', 'shares'])), fn ($v) => $v !== null);
            if ($vals) {
                $idea->update($vals + ['measured_at' => now()]);
                $n++;
            }
        }

        return $n;
    }
}
