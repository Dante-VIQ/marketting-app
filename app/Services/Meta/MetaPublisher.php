<?php

namespace App\Services\Meta;

/** Publishes to a Facebook Page or an Instagram account. Callers decide WHEN and whether a human approved it. */
class MetaPublisher
{
    public function __construct(private GraphClient $graph) {}

    /** @return array{id:string,url:string} */
    public function facebook(array $page, string $message, ?string $link = null, ?string $imageUrl = null): array
    {
        if (empty($page['token'])) {
            throw new MetaApiException('No Page access token available for ' . ($page['name'] ?? 'this Page') . '.');
        }

        if ($imageUrl) {
            // The photos endpoint has no link field, so the link goes in the caption; otherwise an image post sends nobody to the site.
            $caption = $link && !str_contains($message, $link) ? rtrim($message) . "\n\n" . $link : $message;
            $r = $this->graph->post("{$page['id']}/photos", ['url' => $imageUrl, 'caption' => $caption, 'published' => 'true'], $page['token']);
            $id = (string) ($r['post_id'] ?? $r['id'] ?? '');
        } else {
            $params = ['message' => $message] + ($link ? ['link' => $link] : []);
            $r = $this->graph->post("{$page['id']}/feed", $params, $page['token']);
            $id = (string) ($r['id'] ?? '');
        }
        if ($id === '') {
            throw new MetaApiException('Meta accepted the request but returned no post id. Check the Page before retrying.');
        }

        return ['id' => $id, 'url' => "https://www.facebook.com/{$id}"];
    }

    /** Photo posts only: video/Reels need a processing wait and stay manual for now. */
    public function instagram(array $page, string $caption, string $imageUrl): array
    {
        $igId = $page['instagram_id'] ?? null;
        if (!$igId) {
            throw new MetaApiException('This Page has no linked Instagram business account.');
        }
        $tok = $page['token'] ?? null;

        $container = $this->graph->post("{$igId}/media", ['image_url' => $imageUrl, 'caption' => $caption], $tok);
        $creation = (string) ($container['id'] ?? '');
        if ($creation === '') {
            throw new MetaApiException('Instagram did not return a media container id.');
        }

        for ($i = 0; $i < 5; $i++) {
            $status = $this->graph->get($creation, ['fields' => 'status_code'], $tok)['status_code'] ?? 'FINISHED';
            if ($status === 'FINISHED') {
                break;
            }
            if (in_array($status, ['ERROR', 'EXPIRED'], true)) {
                throw new MetaApiException("Instagram could not process the image ({$status}). Check that the URL is public and the image is JPEG.");
            }
            sleep(2);
        }

        $published = $this->graph->post("{$igId}/media_publish", ['creation_id' => $creation], $tok);
        $id = (string) ($published['id'] ?? '');
        if ($id === '') {
            throw new MetaApiException('Instagram did not confirm publishing. Check the account before retrying.');
        }
        $permalink = $this->graph->get($id, ['fields' => 'permalink'], $tok)['permalink'] ?? null;

        return ['id' => $id, 'url' => $permalink ?? "https://www.instagram.com/"];
    }
}
