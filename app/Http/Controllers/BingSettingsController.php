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

            return response()->json([
                'has_api_key'    => true,
                'has_data'       => !empty($data['search_performance']['queries']),
                'keyword_gaps'   => array_slice($data['keyword_gaps'] ?? [], 0, 10),
                'top_queries'    => array_slice($data['search_performance']['queries'] ?? [], 0, 10),
                'backlinks'      => $data['backlinks'] ?? [],
                'top_pages'      => array_slice($data['top_insights'] ?? [], 0, 10),
                'fetched_at'     => $data['fetched_at'] ?? null,
                'errors'         => $data['errors'] ?? [],
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
            'type' => 'required|in:topic_portfolio,ai_performance,citations',
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
