<?php

namespace App\Services\Meta;

use App\Models\AdInsightRow;
use App\Models\Brand;
use Illuminate\Support\Facades\DB;

/**
 * Pulls ad performance from the free Marketing API Insights endpoint, with the audience breakdowns Meta allows
 * (age, gender, country, placement). A breakdown that fails keeps its previous rows; nothing is invented.
 */
class AdInsightsSync
{
    /** breakdown key => [Graph level, Graph breakdowns] */
    public const SETS = [
        'total'      => ['account', []],
        'campaign'   => ['campaign', []],
        'age_gender' => ['account', ['age', 'gender']],
        'country'    => ['account', ['country']],
        'placement'  => ['account', ['publisher_platform', 'platform_position']],
    ];

    private const FIELDS = 'impressions,reach,clicks,spend,account_currency,campaign_id,campaign_name,actions';

    /** @param callable|null $brandConfig fn(int $brandId): array */
    public function __construct(private GraphClient $graph, private $brandConfig = null)
    {
        $this->brandConfig ??= fn (int $id) => Brand::find($id)?->config ?? [];
    }

    /** @return array{rows:int,account:?string,errors:string[]} */
    public function run(int $brandId, int $days = 30): array
    {
        $out = ['rows' => 0, 'account' => null, 'errors' => []];
        try {
            $account = $this->account(($this->brandConfig)($brandId));
        } catch (MetaApiException $e) {
            $out['errors'][] = trim($e->getMessage() . ' ' . $e->hint());

            return $out;
        }
        $out['account'] = $account;
        $range = json_encode(['since' => now()->subDays($days)->toDateString(), 'until' => now()->subDay()->toDateString()]);

        foreach (self::SETS as $key => [$level, $breakdowns]) {
            try {
                $params = ['fields' => self::FIELDS, 'level' => $level, 'time_range' => $range, 'limit' => 500];
                if ($breakdowns) {
                    $params['breakdowns'] = implode(',', $breakdowns);
                }
                $rows = $this->graph->paginate("{$account}/insights", $params);
            } catch (MetaApiException $e) {
                $out['errors'][] = "Ads {$key}: {$e->getMessage()} {$e->hint()}";   // keep what we had for this breakdown

                continue;
            }
            $parsed = array_map(fn ($r) => self::parseRow($r, $key, $breakdowns), $rows);
            DB::transaction(function () use ($brandId, $account, $key, $parsed, &$out) {
                AdInsightRow::where('brand_id', $brandId)->where('ad_account_id', $account)->where('breakdown', $key)->delete();
                foreach ($parsed as $p) {
                    AdInsightRow::create($p + ['brand_id' => $brandId, 'ad_account_id' => $account, 'breakdown' => $key,
                        'row_hash' => sha1(implode('|', [$brandId, $account, $key, $p['dim1'], $p['dim2'], $p['date_start'], $p['date_stop']]))]);
                    $out['rows']++;
                }
            });
        }

        return $out;
    }

    /** Meta sends numbers as strings and omits what it does not know. Missing stays null. */
    public static function parseRow(array $r, string $key, array $breakdowns): array
    {
        $num = fn ($v) => isset($r[$v]) && is_numeric($r[$v]) ? (int) $r[$v] : null;
        $dims = array_map(fn ($b) => isset($r[$b]) ? (string) $r[$b] : null, $breakdowns);
        if ($key === 'campaign') {
            $dims = [isset($r['campaign_id']) ? (string) $r['campaign_id'] : null];
        }
        $leads = null;
        foreach ($r['actions'] ?? [] as $a) {
            if (in_array($a['action_type'] ?? '', ['lead', 'onsite_conversion.lead_grouped'], true) && is_numeric($a['value'] ?? null)) {
                $leads = max((int) $a['value'], (int) $leads);   // the two types describe the same leads; do not add them
            }
        }

        return [
            'dim1' => $dims[0] ?? null, 'dim2' => $dims[1] ?? null,
            'label' => $key === 'campaign' ? ($r['campaign_name'] ?? null) : null,
            'date_start' => $r['date_start'] ?? now()->toDateString(), 'date_stop' => $r['date_stop'] ?? now()->toDateString(),
            'impressions' => $num('impressions'), 'reach' => $num('reach'), 'clicks' => $num('clicks'),
            'spend' => isset($r['spend']) && is_numeric($r['spend']) ? round((float) $r['spend'], 2) : null,
            'results' => $leads, 'currency' => $r['account_currency'] ?? null,
        ];
    }

    private function account(array $brandConfig): string
    {
        $id = (string) ($brandConfig['meta_ad_account_id'] ?? config('meta.ad_account_id') ?? '');
        if ($id === '') {
            $accounts = $this->graph->paginate('me/adaccounts', ['fields' => 'id,name,account_status', 'limit' => 50]);
            if (count($accounts) === 1) {
                $id = (string) $accounts[0]['id'];
            } else {
                throw new MetaApiException(count($accounts) === 0
                    ? 'The token has no ad accounts (needs ads_read and access to an ad account).'
                    : 'Several ad accounts are available; set META_AD_ACCOUNT_ID (or meta_ad_account_id in the brand config).');
            }
        }

        return str_starts_with($id, 'act_') ? $id : 'act_' . $id;
    }
}
