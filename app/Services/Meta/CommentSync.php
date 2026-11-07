<?php

namespace App\Services\Meta;

use App\Models\AudienceComment;
use App\Models\Brand;
use App\Services\Listening\MentionAnalyzer;

/**
 * Saves what people write under the brand's own Facebook and Instagram posts. This is the most direct customer voice
 * there is: questions, objections and praise in their own words. Author names are deliberately not stored.
 */
class CommentSync
{
    private const POSTS = 15;
    private const PER_POST = 50;

    /** @param callable|null $brandConfig fn(int $brandId): array */
    public function __construct(private GraphClient $graph, private MetaAssets $assets, private $brandConfig = null)
    {
        $this->brandConfig ??= fn (int $id) => Brand::find($id)?->config ?? [];
    }

    /** @return array{saved:int,errors:string[]} */
    public function run(int $brandId): array
    {
        $out = ['saved' => 0, 'errors' => []];
        try {
            $page = $this->assets->pageFor(($this->brandConfig)($brandId));
        } catch (MetaApiException $e) {
            $out['errors'][] = trim($e->getMessage() . ' ' . $e->hint());

            return $out;
        }
        $tok = $page['token'] ?? null;
        if (!$tok) {
            $out['errors'][] = 'No Page access token for ' . ($page['name'] ?? 'the Page') . '.';

            return $out;
        }

        $steps = [
            'Facebook comments' => fn () => $this->facebook($brandId, $page, $tok),
            'Instagram comments' => fn () => $this->instagram($brandId, $page, $tok),
        ];
        foreach ($steps as $label => $step) {
            try {
                $out['saved'] += $step();
            } catch (MetaApiException $e) {
                $out['errors'][] = "{$label}: {$e->getMessage()} {$e->hint()}";
            }
        }

        return $out;
    }

    private function facebook(int $brandId, array $page, string $tok): int
    {
        $posts = $this->graph->get("{$page['id']}/posts", ['fields' => 'id', 'limit' => self::POSTS], $tok)['data'] ?? [];
        $n = 0;
        foreach ($posts as $post) {
            $rows = $this->graph->get("{$post['id']}/comments", ['fields' => 'id,message,created_time,like_count', 'limit' => self::PER_POST, 'filter' => 'stream'], $tok)['data'] ?? [];
            foreach ($rows as $c) {
                $n += $this->save($brandId, 'facebook', $c['id'], $post['id'], $c['message'] ?? '', $c['like_count'] ?? null, $c['created_time'] ?? null);
            }
        }

        return $n;
    }

    private function instagram(int $brandId, array $page, string $tok): int
    {
        if (empty($page['instagram_id'])) {
            return 0;
        }
        $media = $this->graph->get("{$page['instagram_id']}/media", ['fields' => 'id', 'limit' => self::POSTS], $tok)['data'] ?? [];
        $n = 0;
        foreach ($media as $m) {
            $rows = $this->graph->get("{$m['id']}/comments", ['fields' => 'id,text,timestamp,like_count', 'limit' => self::PER_POST], $tok)['data'] ?? [];
            foreach ($rows as $c) {
                $n += $this->save($brandId, 'instagram', $c['id'], $m['id'], $c['text'] ?? '', $c['like_count'] ?? null, $c['timestamp'] ?? null);
            }
        }

        return $n;
    }

    private function save(int $brandId, string $platform, string $remoteId, string $postId, string $body, ?int $likes, ?string $at): int
    {
        $body = trim($body);
        if ($body === '') {
            return 0;
        }
        $row = AudienceComment::firstOrNew(['brand_id' => $brandId, 'platform' => $platform, 'remote_id' => $remoteId]);
        $new = !$row->exists;
        $row->fill(['post_remote_id' => $postId, 'body' => mb_substr($body, 0, 2000), 'likes' => $likes,
            'sentiment' => MentionAnalyzer::sentiment($body), 'is_question' => self::isQuestion($body),
            'commented_at' => $at ? \Illuminate\Support\Carbon::parse($at) : null])->save();

        return $new ? 1 : 0;
    }

    /** A question is a buying signal or a gap in the content. */
    public static function isQuestion(string $text): bool
    {
        return str_contains($text, '?') || (bool) preg_match('/\b(how much|price|cost|rates?|where|when|how do|how can|available|inbox|dm)\b/i', $text);
    }
}
