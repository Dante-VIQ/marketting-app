<?php

namespace App\Services\Meta;

/** The Pages (and linked Instagram accounts) the token can act on, with their Page access tokens. */
class MetaAssets
{
    public function __construct(private GraphClient $graph) {}

    /** @return array<string,array{id:string,name:string,token:?string,instagram_id:?string,instagram_username:?string,tasks:array}> keyed by Page id */
    public function pages(): array
    {
        $rows = $this->graph->paginate('me/accounts', [
            'fields' => 'id,name,access_token,tasks,instagram_business_account{id,username}',
            'limit' => 100,
        ]);
        $out = [];
        foreach ($rows as $p) {
            $out[(string) $p['id']] = [
                'id' => (string) $p['id'], 'name' => $p['name'] ?? '', 'token' => $p['access_token'] ?? null,
                'instagram_id' => $p['instagram_business_account']['id'] ?? null,
                'instagram_username' => $p['instagram_business_account']['username'] ?? null,
                'tasks' => $p['tasks'] ?? [],
            ];
        }

        return $out;
    }

    /**
     * Which Page this brand uses: brand config, then env default, then the only Page there is.
     * Several Pages and no choice made is an error, not a guess.
     */
    public function pageFor(array $brandConfig, ?array $pages = null): array
    {
        $wanted = (string) ($brandConfig['meta_page_id'] ?? config('meta.page_id') ?? '');
        $pages ??= $this->pages();
        if ($wanted !== '') {
            return $pages[$wanted] ?? $this->page($wanted);
        }
        if (count($pages) === 1) {
            return reset($pages);
        }

        throw new MetaApiException(count($pages) === 0
            ? 'The token has no Pages (needs pages_show_list and a Page role).'
            : 'Several Pages are available; set META_PAGE_ID (or meta_page_id in the brand config) to choose one.');
    }

    /**
     * One Page by id. A System User token may not list Pages under me/accounts even when a Page is assigned to it,
     * but the Page node itself returns its access token when the user has access.
     */
    public function page(string $id): array
    {
        try {
            $p = $this->graph->get($id, ['fields' => 'id,name,access_token,tasks,instagram_business_account{id,username}']);
        } catch (MetaApiException $e) {
            throw new MetaApiException("The token cannot access Page {$id}: {$e->getMessage()}", $e->httpStatus, $e->metaCode, $e->subcode);
        }
        if (empty($p['access_token'])) {
            throw new MetaApiException("The token can see Page {$id} but Meta gave no Page access token (the user needs a Page role or the Page must be assigned to the System User with full control).");
        }

        return ['id' => (string) $p['id'], 'name' => $p['name'] ?? '', 'token' => $p['access_token'],
            'instagram_id' => $p['instagram_business_account']['id'] ?? null,
            'instagram_username' => $p['instagram_business_account']['username'] ?? null, 'tasks' => $p['tasks'] ?? []];
    }
}
