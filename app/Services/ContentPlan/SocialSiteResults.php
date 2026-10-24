<?php

namespace App\Services\ContentPlan;

use App\Models\Brand;
use App\Models\ContentIdea;
use App\Services\Analytics\GA4Service;

/**
 * Connects posts to the website: how many visits, and how many key events (e.g. generate_lead), each post sent.
 * Works through the UTM tag added when a post is approved (utm_campaign=vumbiai-{id}).
 * Unknown is not zero: a post is only given numbers when it has a tagged link, GA4 answered, and GA4 has had time to process.
 */
class SocialSiteResults
{
    /** GA4 can take up to ~48h to finish processing a day. */
    public const SETTLE_DAYS = 2;
    public const MAX_WINDOW_DAYS = 90;

    /** @return array<int,array{sessions:int,key_events:int}> keyed by content idea id */
    public static function parse(array $response): array
    {
        $names = array_map(fn ($h) => $h['name'] ?? '', $response['metricHeaders'] ?? []);
        $out = [];
        foreach ($response['rows'] ?? [] as $row) {
            $campaign = (string) ($row['dimensionValues'][0]['value'] ?? '');
            if (!preg_match('/^vumbiai-(\d+)$/', $campaign, $m)) {
                continue;
            }
            $vals = [];
            foreach ($row['metricValues'] ?? [] as $i => $mv) {
                if (isset($names[$i])) {
                    $vals[$names[$i]] = (int) round((float) ($mv['value'] ?? 0));
                }
            }
            $id = (int) $m[1];
            $out[$id]['sessions'] = ($out[$id]['sessions'] ?? 0) + ($vals['sessions'] ?? 0);
            $out[$id]['key_events'] = ($out[$id]['key_events'] ?? 0) + ($vals['keyEvents'] ?? 0);
        }

        return $out;
    }

    /** Posts that carry a tag and are old enough for GA4 to have settled. */
    public static function eligible(int $brandId): \Illuminate\Support\Collection
    {
        return ContentIdea::where('brand_id', $brandId)->where('status', 'posted')
            ->where('link_url', 'like', '%utm_campaign=vumbiai-%')
            ->whereNotNull('posted_at')->where('posted_at', '<=', now()->subDays(self::SETTLE_DAYS))->get();
    }

    /** @return array{updated:int,errors:string[]} */
    public function run(Brand $brand, ?GA4Service $ga4 = null): array
    {
        $out = ['updated' => 0, 'errors' => []];
        $ideas = self::eligible($brand->id);
        if ($ideas->isEmpty()) {
            return $out;
        }
        $ga4 ??= app(GA4Service::class);
        $start = max($ideas->min('posted_at')->copy()->startOfDay(), now()->subDays(self::MAX_WINDOW_DAYS)->startOfDay());
        try {
            $response = $ga4->getSocialCampaignReport($brand, $start->toDateString(), now()->toDateString());
        } catch (\Throwable $e) {
            $out['errors'][] = 'GA4: ' . $e->getMessage();   // nothing is stored on failure

            return $out;
        }

        $byId = self::parse($response);
        foreach ($ideas as $idea) {
            if ($idea->posted_at->lt($start)) {
                continue;   // older than the report window: leave it unknown
            }
            $r = $byId[$idea->id] ?? ['sessions' => 0, 'key_events' => 0];   // GA4 answered and settled: no row means no visits
            $idea->update(['site_sessions' => $r['sessions'], 'site_key_events' => $r['key_events'], 'site_measured_at' => now()]);
            $out['updated']++;
        }

        return $out;
    }

    /** Compact, honest block for the brief and the agent. */
    public static function summary(int $brandId): array
    {
        $tracked = ContentIdea::where('brand_id', $brandId)->where('status', 'posted')->whereNotNull('site_measured_at')->get();
        if ($tracked->isEmpty()) {
            $waiting = ContentIdea::where('brand_id', $brandId)->where('status', 'posted')->where('link_url', 'like', '%utm_campaign=vumbiai-%')->count();

            return ['state' => $waiting ? 'too_early' : 'no_tracked_posts', 'tracked_posts' => 0,
                'note' => $waiting ? 'Posts with tracked links exist but GA4 has not settled yet.' : 'No posted content has a tracked link, so the system cannot say what posts did on the site.'];
        }

        return [
            'state' => 'ok',
            'tracked_posts' => $tracked->count(),
            'site_sessions' => (int) $tracked->sum('site_sessions'),
            'key_events' => (int) $tracked->sum('site_key_events'),
            'top_by_site_sessions' => $tracked->sortByDesc('site_sessions')->take(3)->map(fn ($i) => [
                'title' => $i->title, 'platform' => $i->platform, 'format' => $i->format, 'site_sessions' => (int) $i->site_sessions, 'key_events' => (int) $i->site_key_events])->values()->all(),
            'note' => 'Visits and key events counted by GA4 for each tagged post. This is traffic and sign-up signals, not revenue.',
        ];
    }
}
