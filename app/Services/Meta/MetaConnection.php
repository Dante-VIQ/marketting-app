<?php

namespace App\Services\Meta;

/** What the configured token is, what it can do, and what it is missing. Reads only; never throws. */
class MetaConnection
{
    public function __construct(private GraphClient $graph, private MetaAssets $assets) {}

    public function status(): array
    {
        if (!$this->graph->isConfigured()) {
            return ['configured' => false, 'error' => 'META_ACCESS_TOKEN (or META_ADS_ACCESS_TOKEN) is not set in .env.',
                'token' => null, 'capabilities' => MetaCapabilities::evaluate([]), 'missing' => [], 'pages' => [], 'pages_error' => null];
        }

        $id = config('meta.app_id');
        $secret = config('meta.app_secret');
        $inspector = ($id && $secret) ? "{$id}|{$secret}" : null;   // an app token is the most reliable way to inspect a token

        try {
            $token = MetaParsers::debugToken($this->graph->get('debug_token', ['input_token' => $this->graph->token()], $inspector));
            $error = null;
        } catch (MetaApiException $e) {
            $token = null;
            $error = trim($e->getMessage() . ' ' . $e->hint());
        }

        $scopes = $token['scopes'] ?? [];
        $pages = [];
        $pagesError = null;
        if ($token && $token['valid'] && MetaCapabilities::has($scopes, 'pages_list')) {
            try {
                $pages = array_map(fn ($p) => ['id' => $p['id'], 'name' => $p['name'], 'instagram' => $p['instagram_username'], 'can_post' => in_array('CREATE_CONTENT', $p['tasks'], true)],
                    array_values($this->assets->pages()));
            } catch (MetaApiException $e) {
                $pagesError = $e->getMessage();
            }
        }

        return ['configured' => true, 'error' => $error, 'token' => $token, 'capabilities' => MetaCapabilities::evaluate($scopes),
            'missing' => MetaCapabilities::missingForBuilt($scopes), 'pages' => $pages, 'pages_error' => $pagesError];
    }
}
