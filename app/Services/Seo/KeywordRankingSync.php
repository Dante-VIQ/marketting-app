<?php

namespace App\Services\Seo;

use App\Models\AhrefsKeyword;
use App\Models\Brand;
use App\Models\KeywordRanking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Builds keyword_rankings (what the SEO UI and the agent read) from real Ahrefs data.
 * Replaces the old stub that inserted four hard-coded keywords with random positions.
 */
class KeywordRankingSync
{
    /** Ahrefs data older than this is treated as missing rather than shown as current. */
    public const MAX_AGE_DAYS = 3;

    public static function difficultyLabel($kd): ?string
    {
        if ($kd === null || $kd === '' || !is_numeric($kd)) {
            return null;
        }

        return $kd < 30 ? 'easy' : ($kd < 60 ? 'medium' : 'hard');
    }

    /** @return int number of keyword rankings written (0 when no fresh Ahrefs data) */
    public function sync(Brand $brand): int
    {
        $latest = AhrefsKeyword::where('brand_id', $brand->id)->max('tracked_date');

        if (!$latest || Carbon::parse($latest)->lt(Carbon::today()->subDays(self::MAX_AGE_DAYS))) {
            Log::info('KeywordRankingSync: no fresh Ahrefs keyword data; leaving rankings unchanged', [
                'brand_id' => $brand->id,
                'latest' => $latest,
            ]);
            return 0;
        }

        $date  = Carbon::parse($latest)->toDateString();
        $count = 0;

        AhrefsKeyword::where('brand_id', $brand->id)
            ->whereDate('tracked_date', $date)
            ->whereNotNull('position')
            ->orderBy('position')
            ->each(function (AhrefsKeyword $k) use ($brand, $date, &$count) {
                KeywordRanking::updateOrCreate(
                    ['brand_id' => $brand->id, 'keyword' => $k->keyword, 'tracked_date' => $date],
                    [
                        'page_url'          => $k->target_url,
                        'position'          => $k->position,
                        'previous_position' => $k->previous_position,
                        'search_volume'     => $k->search_volume,
                        'difficulty'        => self::difficultyLabel($k->difficulty),
                        'metadata'          => [
                            'source'     => 'ahrefs',
                            'kd'         => $k->difficulty,
                            'traffic'    => $k->traffic,
                            'intent'     => $k->intent,
                            'is_branded' => $k->is_branded,
                        ],
                    ]
                );
                $count++;
            });

        return $count;
    }
}
