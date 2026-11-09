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

        if (!$hasApiKey) {
            return response()->json([
                'has_api_key'    => false,
                'has_data'       => false,
                'keyword_gaps'   => [],
                'top_queries'    => [],
                'fetched_at'     => null,
                'errors'         => [],
            ]);
        }

        try {
            $data = $this->bing->fetchAll($brand);

            // Pull live API queries/gaps
            $liveQueries = $data['search_performance']['queries'] ?? [];
            $liveGaps    = $data['keyword_gaps'] ?? [];

            // Pull imported keyword report data (fallback when no live API data)
            $importedQueries = $this->importedKeywordQueries($brand);
            $importedGaps    = $this->importedKeywordGaps($brand);

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
            'type' => 'required|in:keyword_report,topic_portfolio,ai_performance,citations',
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

            // Store in brand settings — keyed by type with timestamp
            $settings = $brand->settings ?? [];
            $settings['bing_imports'][$type] = [
                'imported_at' => now()->toIso8601String(),
                'row_count'   => $rows,
                'data'        => array_slice($csv, 0, 500), // cap at 500 rows for storage
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

    /**
     * Build query rows from an imported keyword report CSV.
     *
     * Bing keyword report columns: Keyword, Impressions, Clicks, CTR, Avg. Position
     * We normalise these into the same shape as live API query rows so the
     * blade template can display them identically.
     */
    private function importedKeywordQueries(Brand $brand): array
    {
        $rows = ($brand->settings['bing_imports']['topic_portfolio']['data'] ?? [])
            ?: ($brand->settings['bing_imports']['ai_performance']['data']  ?? [])
            ?: [];

        // Keyword report import (the primary CSV Bing exports)
        $kwRows = $brand->settings['bing_imports']['keyword_report']['data'] ?? [];
        if (!empty($kwRows)) {
            $rows = array_merge($kwRows, $rows);
        }

        $queries = [];
        foreach ($rows as $row) {
            // Handle both "Keyword" (Bing keyword report) and "Query" column names
            $keyword = $row['Keyword'] ?? $row['Query'] ?? null;
            if (!$keyword) continue;

            $impressions = (int) str_replace(',', '', $row['Impressions'] ?? 0);
            $clicks      = (int) str_replace(',', '', $row['Clicks']      ?? 0);
            // CTR may be "5.26%" — strip the % sign
            $ctrRaw  = str_replace('%', '', $row['CTR'] ?? '0');
            $ctr     = (float) $ctrRaw / 100; // as a decimal (e.g. 0.0526)
            $avgPos  = (float) ($row['Avg. Position'] ?? $row['AvgPosition'] ?? 0);

            $queries[] = [
                'query'          => trim($keyword),
                'impressions'    => $impressions,
                'clicks'         => $clicks,
                'click_position' => $avgPos,
                'impression_pos' => $avgPos,
                'ctr'            => $ctr,
                'date'           => null,
                'source'         => 'imported', // flag for blade
            ];
        }

        // Sort by impressions descending
        usort($queries, fn($a, $b) => $b['impressions'] <=> $a['impressions']);

        return $queries;
    }

    /**
     * Derive keyword gaps from imported query data using the same logic
     * as BingWebmasterService::deriveKeywordGaps().
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

            if (!$query || $impressions < 10 || $position <= 0) {
                continue;
            }

            $isLowRankHighImpression = $position > 5 && $position <= 30 && $impressions >= 50;
            $isHighImpLowCtr         = $position <= 5 && $ctr < 0.02 && $impressions >= 100;

            if ($isLowRankHighImpression || $isHighImpLowCtr) {
                $gaps[] = [
                    'query'       => $query,
                    'impressions' => $impressions,
                    'position'    => round($position, 1),
                    'clicks'      => $clicks,
                    'ctr'         => round($ctr * 100, 2),
                    'gap_type'    => $isHighImpLowCtr ? 'ctr_mismatch' : 'ranking_gap',
                    'opportunity' => min(100, (int) (min(50, $impressions / 10) + min(50, $position * 1.5))),
                    'source'      => 'imported',
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

        foreach (['keyword_report', 'topic_portfolio', 'ai_performance', 'citations'] as $type) {
            if (!empty($imports[$type])) {
                $summary[$type] = [
                    'row_count'   => $imports[$type]['row_count']   ?? 0,
                    'imported_at' => $imports[$type]['imported_at'] ?? null,
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
