<?php

namespace App\Services\Meta;

use App\Models\Brand;
use App\Models\Lead;

/**
 * Pulls people who filled in a Facebook/Instagram Lead Ad form into the Leads pipeline. Free with leads_retrieval.
 * Each Meta lead is stored once (its id is kept in metadata), so re-running never duplicates.
 */
class LeadSync
{
    /** @param callable|null $brandConfig fn(int $brandId): array */
    public function __construct(private GraphClient $graph, private MetaAssets $assets, private $brandConfig = null)
    {
        $this->brandConfig ??= fn (int $id) => Brand::find($id)?->config ?? [];
    }

    /** @return array{created:int,errors:string[]} */
    public function run(int $brandId): array
    {
        $out = ['created' => 0, 'errors' => []];
        try {
            $page = $this->assets->pageFor(($this->brandConfig)($brandId));
            $tok = $page['token'] ?? throw new MetaApiException('No Page access token for ' . ($page['name'] ?? 'the Page') . '.');
            $forms = $this->graph->paginate("{$page['id']}/leadgen_forms", ['fields' => 'id,name,status', 'limit' => 50], $tok);
        } catch (MetaApiException $e) {
            $out['errors'][] = trim($e->getMessage() . ' ' . $e->hint());

            return $out;
        }

        foreach ($forms as $form) {
            try {
                $leads = $this->graph->paginate("{$form['id']}/leads", ['fields' => 'id,created_time,field_data,ad_name,campaign_name', 'limit' => 100], $tok);
            } catch (MetaApiException $e) {
                $out['errors'][] = "Form {$form['name']}: {$e->getMessage()} {$e->hint()}";

                continue;
            }
            foreach ($leads as $l) {
                if ($this->exists($brandId, (string) $l['id'])) {
                    continue;
                }
                Lead::create(self::toLead($l, $form['name'] ?? null) + ['brand_id' => $brandId]);
                $out['created']++;
            }
        }

        return $out;
    }

    private function exists(int $brandId, string $metaId): bool
    {
        return Lead::where('brand_id', $brandId)->where('metadata->meta_lead_id', $metaId)->exists();
    }

    /** Maps the form's field_data to lead columns. Anything unrecognised is kept, not dropped. */
    public static function toLead(array $l, ?string $formName): array
    {
        $f = [];
        foreach ($l['field_data'] ?? [] as $d) {
            $v = is_array($d['values'] ?? null) ? trim(implode(', ', $d['values'])) : '';
            if ($v !== '') {
                $f[strtolower((string) ($d['name'] ?? ''))] = $v;
            }
        }
        $pull = function (array $names) use (&$f) {
            foreach ($names as $n) {
                if (isset($f[$n])) {
                    $v = $f[$n];
                    unset($f[$n]);

                    return $v;
                }
            }

            return null;
        };
        $first = $pull(['first_name']);
        $last = $pull(['last_name']);
        if ($first === null && $last === null && ($full = $pull(['full_name', 'name']))) {
            $parts = preg_split('/\s+/', $full, 2);
            $first = $parts[0];
            $last = $parts[1] ?? null;
        }

        return [
            'first_name' => $first, 'last_name' => $last,
            'email' => $pull(['email', 'work_email']), 'phone' => $pull(['phone_number', 'phone']),
            'company' => $pull(['company_name', 'company']), 'title' => $pull(['job_title']),
            'message' => $f ? collect($f)->map(fn ($v, $k) => str_replace('_', ' ', $k) . ': ' . $v)->implode("\n") : null,
            'source' => 'social', 'status' => 'new',
            'metadata' => array_filter(['meta_lead_id' => (string) ($l['id'] ?? ''), 'meta_form' => $formName,
                'meta_campaign' => $l['campaign_name'] ?? null, 'meta_ad' => $l['ad_name'] ?? null, 'meta_created_time' => $l['created_time'] ?? null]),
        ];
    }
}
