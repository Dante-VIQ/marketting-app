<?php

namespace App\Services\Meta;

/** Reads post results. A metric Meta no longer offers is skipped and reported, never turned into a zero. */
class MetaInsights
{
    public function __construct(private GraphClient $graph) {}

    /** @return array{0:array,1:string[]} [insights response with only the metrics that worked, errors] */
    public function tolerant(string $path, array $metrics, string $token): array
    {
        try {
            return [$this->graph->get($path, ['metric' => implode(',', $metrics)], $token), []];
        } catch (MetaApiException $e) {
            if (!in_array($e->kind(), ['invalid_parameter', 'permission'], true)) {
                throw $e;
            }
        }

        // One bad metric name fails the whole call, so retry each on its own.
        $data = [];
        $errors = [];
        foreach ($metrics as $m) {
            try {
                $data = array_merge($data, $this->graph->get($path, ['metric' => $m], $token)['data'] ?? []);
            } catch (MetaApiException $e) {
                $errors[] = "{$m}: {$e->getMessage()}";
            }
        }

        return [['data' => $data], $errors];
    }

    public function facebookPost(string $postId, string $pageToken): array
    {
        $post = $this->graph->get($postId, ['fields' => 'reactions.summary(true).limit(0),comments.summary(true).limit(0),shares'], $pageToken);
        [$ins, $errors] = $this->tolerant("{$postId}/insights", config('meta.insights.facebook_post'), $pageToken);

        return MetaParsers::facebookPost($post, $ins) + ['errors' => $errors];
    }

    public function instagramMedia(string $mediaId, string $token): array
    {
        $media = $this->graph->get($mediaId, ['fields' => 'like_count,comments_count'], $token);
        [$ins, $errors] = $this->tolerant("{$mediaId}/insights", config('meta.insights.instagram_media'), $token);

        return MetaParsers::instagramMedia($media, $ins) + ['errors' => $errors];
    }
}
