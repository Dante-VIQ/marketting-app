<?php

namespace App\Services;

use App\Models\ContentDraft;
use App\Models\TourPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ContentTourMatcher
{
    // Score thresholds
    private const MIN_MATCH_SCORE = 3.0;       // below this, no match
    private const ENFORCE_SCORE   = 8.0;       // above this, enforce automatically

    /**
     * Find the best tour matches for a content draft.
     * Returns a collection sorted by score descending.
     */
    public function findMatches(ContentDraft $draft): Collection
    {
        $tours = TourPackage::where('brand_id', $draft->brand_id)
            ->active()
            ->get();

        if ($tours->isEmpty()) {
            return collect();
        }

        $content = $this->buildSearchCorpus($draft);
        $contentLower = mb_strtolower($content);

        return $tours
            ->map(function (TourPackage $tour) use ($contentLower) {
                $score = $this->calculateMatchScore($contentLower, $tour);
                return ['tour' => $tour, 'score' => $score];
            })
            ->filter(fn($item) => $item['score'] >= self::MIN_MATCH_SCORE)
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Determine if a match should be auto-enforced.
     */
    public function shouldEnforce(Collection $matches): bool
    {
        $best = $matches->first();
        return $best && $best['score'] >= self::ENFORCE_SCORE;
    }

    /**
     * Apply the best match to the draft.
     */
    public function applyMatch(ContentDraft $draft, Collection $matches): ContentDraft
    {
        $best = $matches->first();

        $draft->tour_package_id       = $best['tour']->id;
        $draft->affiliate_url         = $best['tour']->affiliate_url;
        $draft->tour_match_score      = $best['score'];
        $draft->tour_match_candidates = $matches->take(3)->map(fn($m) => [
            'tour_id'   => $m['tour']->id,
            'tour_name' => $m['tour']->name,
            'score'     => round($m['score'], 2),
        ])->values()->toArray();
        $draft->tour_enforced = $this->shouldEnforce($matches);

        $draft->save();

        Log::info('Tour match applied', [
            'draft_id' => $draft->id,
            'tour_id'  => $best['tour']->id,
            'score'    => round($best['score'], 2),
            'enforced' => $draft->tour_enforced,
        ]);

        return $draft;
    }

    /**
     * Build a search corpus from draft fields.
     */
    private function buildSearchCorpus(ContentDraft $draft): string
    {
        $parts = [
            $draft->title ?? '',
            $draft->excerpt ?? '',
            $draft->meta_description ?? '',
            $draft->content ?? '',
        ];

        // Include tags if the model has them
        if (method_exists($draft, 'tags') && is_array($draft->tags)) {
            $parts[] = implode(' ', $draft->tags);
        }

        return implode(' ', $parts);
    }

    /**
     * Score how well a tour matches the content.
     */
    private function calculateMatchScore(string $contentLower, TourPackage $tour): float
    {
        $score = 0.0;

        // 1. Destination match (highest weight)
        if ($tour->destination) {
            $dest = mb_strtolower($tour->destination);
            if (str_contains($contentLower, $dest)) {
                $score += 4.0;
            } else {
                // Partial match — split destination into words
                foreach (explode(' ', $dest) as $word) {
                    if (mb_strlen($word) > 3 && str_contains($contentLower, $word)) {
                        $score += 1.0;
                    }
                }
            }
        }

        // 2. Country match
        if ($tour->country) {
            $country = mb_strtolower($tour->country);
            if (str_contains($contentLower, $country)) {
                $score += 2.0;
            }
        }

        // 3. Tour name keywords
        $nameWords = $this->extractKeywords($tour->name);
        foreach ($nameWords as $word) {
            if (str_contains($contentLower, $word)) {
                $score += 1.5;
            }
        }

        // 4. Custom keywords field
        if (!empty($tour->keywords)) {
            foreach ($tour->keywords as $keyword) {
                $kw = mb_strtolower($keyword);
                if (str_contains($contentLower, $kw)) {
                    $score += 2.0;
                }
            }
        }

        // 5. Duration match (article mentions "5 days" → tour is 5 days)
        if ($tour->duration_days && preg_match('/(\d+)\s*[- ]?days?/i', $contentLower, $m)) {
            if ((int) $m[1] === $tour->duration_days) {
                $score += 3.0;
            }
        }

        // 6. Price band match (article mentions "budget" → cheap tour)
        if ($tour->price) {
            if (preg_match('/\b(budget|cheap|affordable)\b/i', $contentLower) && $tour->price < 1000) {
                $score += 1.5;
            }
            if (preg_match('/\b(luxury|premium|exclusive)\b/i', $contentLower) && $tour->price > 3000) {
                $score += 1.5;
            }
        }

        return $score;
    }

    /**
     * Extract meaningful words from a string.
     */
    private function extractKeywords(string $text): array
    {
        $stopWords = [
            'the', 'and', 'for', 'with', 'from', 'this', 'that', 'tour',
            'package', 'trip', 'safari', 'tour', 'travel',
        ];

        $words = preg_split('/\W+/', mb_strtolower($text));
        $words = array_filter($words, fn($w) => mb_strlen($w) > 3);

        return array_values(array_unique(array_diff($words, $stopWords)));
    }
}