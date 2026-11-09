<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Services\Bing\BingWebmasterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * BingSettingsController
 *
 * Manages the Bing Webmaster Tools integration for a brand:
 * - Save / remove the API key (stored in brand settings JSON column)
 * - Trigger a manual data refresh
 * - Accept optional CSV uploads for Topic Portfolio, AI Performance, Citations
 */
class BingSettingsController extends Controller
{
    public function __construct(
        private readonly BingWebmasterService $bing,
    ) {}

    /**
     * Save the Bing API key for the brand.
     * Key is stored in settings['bing_api_key'], never in env or code.
     */
    public function saveApiKey(Request $request, Brand $brand): JsonResponse
    {
        $this->authoriseBrand($brand);

        $request->validate([
            'bing_api_key' => 'required|string|min:10|max:200',
        ]);

        $settings = $brand->settings ?? [];
        $settings['bing_api_key'] = trim($request->bing_api_key);
        $brand->settings = $settings;
        $brand->save();

        Log::info('Bing API key saved', ['brand_id' => $brand->id]);

        return response()->json(['success' => true, 'message' => 'Bing API key saved.']);
    }

    /**
     * Remove the Bing API key from the brand settings.
     */
    public function removeApiKey(Brand $brand): JsonResponse
    {
        $this->authoriseBrand($brand);

        $settings = $brand->settings ?? [];
        unset($settings['bing_api_key']);
        $brand->settings = $settings;
        $brand->save();

        Log::info('Bing API key removed', ['brand_id' => $brand->id]);

        return response()->json(['success' => true, 'message' => 'Bing API key removed.']);
    }

    /**
     * Force-refresh Bing data for a brand (clears cache and re-fetches).
     */
    public function refresh(Brand $brand): JsonResponse
    {
        $this->authoriseBrand($brand);

        try {
            $data = $this->bing->refresh($brand);

            $summary = [
                'fetched_at'         => $data['fetched_at'] ?? null,
                'keyword_gaps_count' => count($data['keyword_gaps'] ?? []),
                'queries_count'      => count($data['search_performance']['queries'] ?? []),
                'errors'             => $data['errors'] ?? [],
            ];

            return response()->json([
                'success' => true,
                'message' => 'Bing data refreshed.',
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            Log::error('Bing refresh failed', ['brand_id' => $brand->id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Bing refresh failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the current Bing data summary for display in the settings panel.
     */
    public function status(Brand $brand): JsonResponse
    {
        $this->authoriseBrand($brand);

        $settings   = $brand->settings ?? [];
        $hasApiKey  = !empty($settings['bing_api_key']);

        // Even without an API key, imported CSVs can still power the dashboard
        $importedQueries = $this->importedKeywordQueries($brand);
        $importedGaps    = $this->importedKeywordGaps($brand);
        $hasImports      = !empty($importedQueries);

        if (!$hasApiKey) {
            return response()->json([
                'has_api_key'   => false,
                'has_data'      => $hasImports,
                'keyword_gaps'  => array_slice($importedGaps, 0, 20),
                'top_queries'   => array_slice($importedQueries, 0, 20),
                'backlinks'     => [],
                'top_pages'     => [],
                'fetched_at'    => null,
                'errors'        => [],
                'has_imports'   => $hasImports,
                'import_counts' => $this->importCounts($brand),
            ]);
        }

        try {
            $data = $this->bing->fetchAll($brand);

            // Pull live API queries/gaps
            $liveQueries = $data['search_performance']['queries'] ?? [];
            $liveGaps    = $data['keyword_gaps'] ?? [];

            // $importedQueries / $importedGaps / $hasImports already computed above

            // Merge: live data takes precedence; imports fill the gap when API returns nothing
            $topQueries = !empty($liveQueries)
                ? array_slice($liveQueries, 0, 20)
                : array_slice($importedQueries, 0, 20);

            $topGaps = !empty($liveGaps)
                ? array_slice($liveGaps, 0, 20)
                : array_slice($importedGaps, 0, 20);

            $hasData = !empty($liveQueries) || !empty($importedQueries);

            return response()->json([
                'has_api_key'      => true,
                'has_data'         => $hasData,
                'keyword_gaps'     => $topGaps,
                'top_queries'      => $topQueries,
                'backlinks'        => $data['backlinks'] ?? [],
                'top_pages'        => array_slice($data['top_insights'] ?? [], 0, 10),
                'fetched_at'       => $data['fetched_at'] ?? null,
                'errors'           => $data['errors'] ?? [],
                'has_imports'      => !empty($importedQueries),
                'import_counts'    => $this->importCounts($brand),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'has_api_key' => true,
                'has_data'    => false,
                'error'       => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import optional CSV exports from Bing Webmaster Tools.
     *
     * Accepts: topic_portfolio, ai_performance, citations
     * These are not available via API — only as manual exports.
     * They are stored in brand settings as supplementary context.
     * Missing files don't block anything — they just enrich context further.
     */
    public function importCsv(Request $request, Brand $brand): JsonResponse
    {
        $this->authoriseBrand($brand);

        $request->validate([
            'type' => 'required|in:keyword_report,search_performance,topic_portfolio,ai_performance,citations',
            'file' => 'required|file|mimes:csv,txt|max:5120', // 5MB max
        ]);

        $type = $request->type;
        $file = $request->file('file');

        try {
            $csv  = $this->parseCsv($file->getRealPath());
            $rows = count($csv);

            if ($rows === 0) {
                return response()->json(['success' => false, 'message' => 'CSV file is empty.'], 422);
            }

            // Detect column names from the first row for diagnostics
            $detectedColumns = !empty($csv) ? array_keys($csv[0]) : [];

            // Store in brand settings — keyed by type with timestamp
            $settings = $brand->settings ?? [];
            $settings['bing_imports'][$type] = [
                'imported_at'      => now()->toIso8601String(),
                'row_count'        => $rows,
                'detected_columns' => $detectedColumns,
                'data'             => array_slice($csv, 0, 500), // cap at 500 rows for storage
            ];
            $brand->settings = $settings;
            $brand->save();

            Log::info('Bing CSV imported', ['brand_id' => $brand->id, 'type' => $type, 'rows' => $rows]);

            return response()->json([
                'success' => true,
                'message' => "Imported {$rows} rows for " . str_replace('_', ' ', $type) . '.',
                'rows'    => $rows,
            ]);
        } catch (\Throwable $e) {
            Log::error('Bing CSV import failed', ['brand_id' => $brand->id, 'type' => $type, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─── Import data readers ─────────────────────────────────────────────────

    /**
     * Extract query rows from ALL imported CSVs that contain keyword/query data.
     *
     * Bing exports different CSV types, each with different column names.
     * We use auto-detection: resolve() maps the actual column headers found in
     * the CSV to our internal field names, so any Bing export format works
     * regardless of minor column name differences between report versions.
     *
     * Types that contribute to query display:
     *   keyword_report    — Keyword, Impressions, Clicks, CTR, Avg. Position
     *   search_performance — Query, Impressions, Clicks, CTR, Avg. Position (same shape)
     *   ai_performance    — Query/Keyword, Impressions, Citations, CTR, Avg. Position
     *   topic_portfolio   — Topic/Keyword, Impressions, Citations, Opportunity queries
     *   citations         — URL/Page, Impressions, Citations — contributes page info, not queries
     */
    private function importedKeywordQueries(Brand $brand): array
    {
        $imports = $brand->settings['bing_imports'] ?? [];
        $queries = [];

        // Types that have a keyword/query column worth showing in Real Queries
        $queryTypes = ['keyword_report', 'search_performance', 'ai_performance', 'topic_portfolio'];

        foreach ($queryTypes as $type) {
            $rows = $imports[$type]['data'] ?? [];
            if (empty($rows)) continue;

            foreach ($rows as $row) {
                $normalized = $this->normalizeRow($row);
                $keyword = $normalized['query'] ?? null;
                if (!$keyword) continue;

                $queries[] = [
                    'query'          => $keyword,
                    'impressions'    => $normalized['impressions'],
                    'clicks'         => $normalized['clicks'],
                    'click_position' => $normalized['position'],
                    'impression_pos' => $normalized['position'],
                    'ctr'            => $normalized['ctr'],
                    'citations'      => $normalized['citations'],
                    'date'           => null,
                    'source'         => 'imported',
                    'source_type'    => $type,
                ];
            }
        }

        // De-duplicate by query string — keep the row with highest impressions
        $deduped = [];
        foreach ($queries as $q) {
            $key = strtolower(trim($q['query']));
            if (!isset($deduped[$key]) || $q['impressions'] > $deduped[$key]['impressions']) {
                $deduped[$key] = $q;
            }
        }

        $result = array_values($deduped);
        usort($result, fn($a, $b) => $b['impressions'] <=> $a['impressions']);

        return $result;
    }

    /**
     * Auto-detect and normalise a CSV row from any Bing export format.
     *
     * Bing uses different column names across report types and sometimes adds
     * trailing spaces or uses title-case vs sentence-case. We resolve each
     * field by trying a list of known aliases, case-insensitively.
     */
    private function normalizeRow(array $row): array
    {
        // Build a lowercase key map so lookups are case-insensitive
        $lc = [];
        foreach ($row as $k => $v) {
            $lc[strtolower(trim($k))] = trim((string) $v);
        }

        $resolve = function (array $aliases) use ($lc): string {
            foreach ($aliases as $alias) {
                $val = $lc[strtolower($alias)] ?? null;
                if ($val !== null && $val !== '') return $val;
            }
            return '';
        };

        $parseNum = fn(string $v): float => (float) str_replace([',', '%', ' '], '', $v);

        // Field aliases — ordered by most common first
        $queryVal  = $resolve(['keyword', 'query', 'topic', 'search query', 'search term', 'term', 'phrase']);
        $impVal    = $resolve(['impressions', 'impression', 'ai impressions', 'total impressions']);
        $clicksVal = $resolve(['clicks', 'click', 'total clicks']);
        $ctrVal    = $resolve(['ctr', 'click through rate', 'clickthrough rate']);
        $posVal    = $resolve(['avg. position', 'avg position', 'average position', 'position', 'avg. rank', 'rank']);
        $citVal    = $resolve(['citations', 'citation', 'ai citations', 'cited', 'citation count']);

        // CTR: Bing exports it as "5.26%" — normalise to decimal
        $ctrRaw = $parseNum($ctrVal);
        // If value is > 1, it was stored as percentage (e.g. 5.26 means 5.26%) — convert
        $ctr = $ctrRaw > 1 ? $ctrRaw / 100 : $ctrRaw;

        return [
            'query'       => $queryVal,
            'impressions' => (int) $parseNum($impVal),
            'clicks'      => (int) $parseNum($clicksVal),
            'ctr'         => $ctr,
            'position'    => (float) $parseNum($posVal),
            'citations'   => (int) $parseNum($citVal),
        ];
    }

    /**
     * Derive keyword gaps from imported query data.
     * Uses same thresholds as BingWebmasterService::deriveKeywordGaps().
     * Lowered impression threshold to 5 for imported data (Bing exports are
     * often small-volume sites where 50 impressions is rare).
     */
    private function importedKeywordGaps(Brand $brand): array
    {
        $queries = $this->importedKeywordQueries($brand);
        $gaps    = [];

        foreach ($queries as $q) {
            $impressions = (int)   $q['impressions'];
            $position    = (float) $q['impression_pos'];
            $clicks      = (int)   $q['clicks'];
            $ctr         = (float) $q['ctr'];
            $query       = trim($q['query']);

            if (!$query || $impressions < 3 || $position <= 0) {
                continue;
            }

            // Ranking gap: visible (position 6–30) but not on first page
            $isLowRankHighImpression = $position > 5 && $position <= 30 && $impressions >= 5;
            // CTR gap: ranking well but very few clicks — title/meta mismatch
            $isHighImpLowCtr = $position <= 5 && $ctr < 0.02 && $impressions >= 10;

            if ($isLowRankHighImpression || $isHighImpLowCtr) {
                $gaps[] = [
                    'query'       => $query,
                    'impressions' => $impressions,
                    'position'    => round($position, 1),
                    'clicks'      => $clicks,
                    'ctr'         => round($ctr * 100, 2),
                    'gap_type'    => $isHighImpLowCtr ? 'ctr_mismatch' : 'ranking_gap',
                    'opportunity' => min(100, (int) (min(50, $impressions * 2) + min(50, $position * 1.5))),
                    'source'      => 'imported',
                    'source_type' => $q['source_type'] ?? 'imported',
                ];
            }
        }

        usort($gaps, fn($a, $b) => $b['opportunity'] <=> $a['opportunity']);

        return array_slice($gaps, 0, 30);
    }

    /**
     * Return a summary of which CSV types have been imported and when.
     */
    private function importCounts(Brand $brand): array
    {
        $imports = $brand->settings['bing_imports'] ?? [];
        $summary = [];

        $allTypes = ['keyword_report', 'search_performance', 'topic_portfolio', 'ai_performance', 'citations'];
        foreach ($allTypes as $type) {
            if (!empty($imports[$type])) {
                $summary[$type] = [
                    'row_count'   => $imports[$type]['row_count']   ?? 0,
                    'imported_at' => $imports[$type]['imported_at'] ?? null,
                    'columns'     => $imports[$type]['detected_columns'] ?? [],
                ];
            }
        }

        return $summary;
    }

    /**
     * Parse a CSV file into an array of associative rows.
     */
    private function parseCsv(string $path): array
    {
        $rows    = [];
        $handle  = fopen($path, 'r');
        $headers = null;

        while (($line = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map('trim', $line);
                continue;
            }

            if (count($line) !== count($headers)) {
                continue; // skip malformed rows
            }

            $rows[] = array_combine($headers, array_map('trim', $line));
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Ensure the authenticated user has access to this brand.
     */
    private function authoriseBrand(Brand $brand): void
    {
        $user = auth()->user();

        if (!$user->brands()->where('brands.id', $brand->id)->exists() && !$user->hasRole('super_admin')) {
            abort(403, 'Access denied.');
        }
    }
}
