<?php

namespace App\Services\Competitors\Discovery;

use App\Models\Brand;
use App\Models\Competitor;
use App\Models\CompetitorSuggestion;
use App\Services\Competitors\CompetitorScanner;
use DomainException;
use Illuminate\Support\Facades\Cache;

/**
 * Finds competitors from what the system already knows about the brand. It only SUGGESTS:
 * a human accepts or dismisses each one (dismissed ones are remembered and never come back).
 * Every candidate's site is fetched and compared with the brand's own vocabulary; unreachable sites are dropped, not scored zero.
 */
class CompetitorDiscovery
{
    public function __construct(private CandidateFinder $finder, private CompetitorScanner $scanner)
    {
    }

    public static function statusKey(int $brandId): string
    {
        return "competitor-discovery:{$brandId}";
    }

    /** @return array{found:int,notes:string[],used_search:bool} */
    public function run(Brand $brand): array
    {
        $profile = BrandProfile::build($brand);
        if (BrandProfile::isThin($profile)) {
            throw new DomainException('The system does not know enough about this brand yet. Add a business description or content pillars to the knowledge base, or let the site scan finish, then try again.');
        }

        $found = $this->finder->find($profile);
        $notes = $found['notes'];

        $skip = Competitor::where('brand_id', $brand->id)->pluck('domain')->all();
        $closed = CompetitorSuggestion::where('brand_id', $brand->id)->whereIn('status', ['accepted', 'dismissed'])->pluck('domain')->all();
        $skip = array_flip([...$skip, ...$closed]);

        $big = config('competitors.big_platforms', []);
        $bigLeft = (int) config('competitors.max_big_platforms', 5);
        $checkLeft = (int) config('competitors.max_candidates_to_check', 20);
        $newLeft = (int) config('competitors.max_new_suggestions', 12);

        // Most evidence first: your own notes, then repeated search hits, then AI-only.
        $cands = $found['candidates'];
        uasort($cands, fn ($a, $b) => [in_array('your_notes', $b['sources']), $b['queries']] <=> [in_array('your_notes', $a['sources']), $a['queries']]);

        $stored = 0;
        $unreachable = 0;
        foreach ($cands as $domain => $c) {
            if (isset($skip[$domain])) {
                continue;
            }
            if (Relevance::isBigPlatform($domain, $big)) {
                if ($bigLeft-- > 0) {
                    $this->save($brand->id, $domain, $c, 'big_platform', null, [], null, 'A large platform that shows up for the same searches. It competes for attention, not like-for-like, so it is not added automatically.');
                    $stored++;
                }
                continue;
            }
            if ($checkLeft-- <= 0 || $newLeft <= 0) {
                continue;
            }

            $probe = $this->scanner->probe($domain);
            if (!$probe['ok']) {
                $unreachable++;
                continue;   // could not be checked: leave it out rather than guess
            }
            $text = implode(' . ', array_filter([$probe['title'], $probe['description'], $probe['h1'], $probe['text'], ...$c['snippets']]));
            $ov = Relevance::overlap($profile['terms'], $text);
            $trusted = in_array('your_notes', $c['sources'], true);
            if ($ov['score'] === 0 && !$trusted) {
                continue;   // reachable but shares none of the brand's vocabulary
            }

            $class = Relevance::classify($text, $probe['has_feed']);
            $this->save($brand->id, $domain, $c, $class, $ov['score'], $ov['shared'], $probe['title'], self::reason($c, $ov, $class));
            $stored++;
            $newLeft--;
        }
        if ($unreachable) {
            $notes[] = "{$unreachable} candidate " . ($unreachable === 1 ? 'site' : 'sites') . " could not be reached and " . ($unreachable === 1 ? 'was' : 'were') . ' left out.';
        }

        return ['found' => $stored, 'notes' => $notes, 'used_search' => $found['used_search']];
    }

    /** Plain-language reason, built only from what was actually observed. */
    public static function reason(array $c, array $ov, string $class): string
    {
        $parts = [];
        if ($ov['shared']) {
            $parts[] = 'Their site uses ' . count($ov['shared']) . ' of your top ' . $ov['of'] . ' topic words (' . implode(', ', array_slice($ov['shared'], 0, 6)) . ').';
        }
        if ($c['queries'] > 0) {
            $parts[] = 'Appeared in ' . $c['queries'] . ' of the searches a customer would make.';
        }
        if (in_array('your_notes', $c['sources'], true)) {
            $parts[] = 'Listed in your brand notes.';
        }
        if (in_array('ai', $c['sources'], true) && $c['why']) {
            $parts[] = 'AI suggestion (' . rtrim($c['why'], '.') . '); we confirmed the site exists but the AI judgement is unverified.';
        } elseif (in_array('ai', $c['sources'], true)) {
            $parts[] = 'Suggested by AI; we confirmed the site exists.';
        }

        return implode(' ', $parts) ?: 'Found by discovery.';
    }

    private function save(int $brandId, string $domain, array $c, string $class, ?int $score, array $shared, ?string $title, string $reason): void
    {
        $row = CompetitorSuggestion::where('brand_id', $brandId)->where('domain', $domain)->first();
        if ($row && $row->status !== 'suggested') {
            return;   // accepted/dismissed decisions are never overwritten
        }
        CompetitorSuggestion::updateOrCreate(['brand_id' => $brandId, 'domain' => $domain], [
            'name' => $c['name'] ?? null, 'sources' => $c['sources'], 'classification' => $class, 'score' => $score,
            'shared_terms' => $shared ?: null, 'query_hits' => min(255, (int) $c['queries']), 'reason' => $reason,
            'homepage_title' => $title ? mb_substr($title, 0, 250) : null, 'status' => 'suggested',
        ]);
    }

    /** Remember the last run so the page can show it (suggestions themselves are the durable result). */
    public static function remember(int $brandId, array $status): void
    {
        Cache::put(self::statusKey($brandId), $status + ['at' => now()->toDateTimeString()], now()->addDays(40));
    }
}
