<?php

namespace App\Services\Scanner;

use App\Models\Brand;
use App\Models\AiAction;
use App\Models\PageSnapshot;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PageScannerService
{
    protected array $config;
    protected ?Brand $brand = null;
    protected ?AiAction $action = null;

    public function __construct()
    {
        $this->config = [
            'timeout' => 30,
            'max_links' => 100,
            'user_agent' => 'VumbiAI-Scanner/1.0 (+https://vumbi-ai.com)',
        ];
    }

    /**
     * Scan a single page and store the snapshot with deduplication.
     */
    public function scanPage(string $url, Brand $brand, ?AiAction $action = null): PageSnapshot
    {
        $this->brand = $brand;
        $this->action = $action;

        $normalizedUrl = $this->normalizeUrl($url);

        Log::info('PageScanner: Starting scan', [
            'brand_id' => $brand->id,
            'url' => $normalizedUrl,
            'action_id' => $action?->id,
        ]);

        // Check if page already exists
        $existing = PageSnapshot::getLatestForUrl($normalizedUrl, $brand->id);

        // Create a new snapshot record (or update existing)
        $snapshot = PageSnapshot::updateOrCreate(
            [
                'brand_id' => $brand->id,
                'url' => $normalizedUrl,
            ],
            [
                'action_id' => $action?->id,
                'status' => 'processing',
                'scraped_at' => now(),
            ]
        );

        // If we have an existing snapshot, keep the action_id if not provided
        if ($existing && !$action) {
            $snapshot->action_id = $existing->action_id;
        }

        try {
            $pageData = $this->fetchPage($normalizedUrl);

            if (!$pageData) {
                throw new \Exception('Failed to fetch page content');
            }

            $parsedData = $this->parseHtml($pageData['html'], $normalizedUrl);
            $analysis = $this->analyzeContent($parsedData);
            $gaps = $this->identifyGaps($analysis, $action);
            $recommendations = $this->generateRecommendations($analysis, $gaps, $action);

            // Check if content has changed significantly
            $contentChanged = false;
            if ($existing) {
                $contentChanged = $existing->hasContentChanged(array_merge($parsedData, $analysis));
            } else {
                $contentChanged = true; // New page
            }

            // If content hasn't changed, skip updating
            if (!$contentChanged && $existing) {
                Log::info('PageScanner: Content unchanged, skipping update', [
                    'snapshot_id' => $snapshot->id,
                    'url' => $normalizedUrl,
                ]);

                $snapshot->update([
                    'status' => 'completed',
                    'scraped_at' => now(),
                ]);

                return $snapshot;
            }

            // Update with new data
            $snapshot->update(array_merge(
                $parsedData,
                $analysis,
                [
                    'content_gaps' => $gaps,
                    'recommendations' => $recommendations,
                    'load_time_ms' => $pageData['load_time_ms'] ?? null,
                    'status' => 'completed',
                    'scraped_at' => now(),
                                          'metadata' => [
                                              'content_changed' => $contentChanged,
                                              'previous_snapshot_id' => $existing?->id,
                                              'scanned_at' => now()->toDateTimeString(),
                                          ],
                ]
            ));

            Log::info('PageScanner: Scan completed', [
                'snapshot_id' => $snapshot->id,
                'url' => $normalizedUrl,
                'word_count' => $snapshot->word_count,
                'content_changed' => $contentChanged,
            ]);

            return $snapshot;

        } catch (\Exception $e) {
            Log::error('PageScanner: Scan failed', [
                'url' => $normalizedUrl,
                'error' => $e->getMessage(),
            ]);

            $snapshot->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if a URL has been scanned recently.
     */
    public function isRecentlyScanned(string $url, int $brandId, int $hours = 24): bool
    {
        $snapshot = PageSnapshot::where('brand_id', $brandId)
        ->where('url', $this->normalizeUrl($url))
        ->where('status', 'completed')
        ->where('created_at', '>=', now()->subHours($hours))
        ->first();

        return (bool) $snapshot;
    }

    /**
     * Get the most recent snapshot for a URL.
     */
    public function getLatestSnapshot(string $url, int $brandId): ?PageSnapshot
    {
        return PageSnapshot::where('brand_id', $brandId)
        ->where('url', $this->normalizeUrl($url))
        ->orderBy('created_at', 'desc')
        ->first();
    }

    /**
     * Scan all pages with deduplication.
     */
    public function scanAllPages(Brand $brand, string $startUrl, int $depth = 2): array
    {
        $this->brand = $brand;
        $results = [];

        $normalizedStartUrl = $this->normalizeUrl($startUrl);
        $urlsToScan = [$normalizedStartUrl];
        $scannedUrls = [];
        $currentDepth = 0;

        while (!empty($urlsToScan) && $currentDepth < $depth) {
            $currentUrls = $urlsToScan;
            $urlsToScan = [];
            $currentDepth++;

            foreach ($currentUrls as $url) {
                // Skip if already scanned in this session or recently scanned
                if (in_array($url, $scannedUrls)) {
                    continue;
                }

                // Skip if scanned within the last 24 hours
                if ($this->isRecentlyScanned($url, $brand->id, 24)) {
                    Log::info('PageScanner: Skipping recently scanned URL', ['url' => $url]);
                    $scannedUrls[] = $url;
                    continue;
                }

                try {
                    $snapshot = $this->scanPage($url, $brand);
                    $results[] = $snapshot;
                    $scannedUrls[] = $url;

                    // Add internal links for deeper scanning (only if depth allows)
                    if ($currentDepth < $depth && $snapshot->internal_links) {
                        foreach ($snapshot->internal_links as $link) {
                            $normalizedLink = $this->normalizeUrl($link['url']);
                            if ($normalizedLink
                                && !in_array($normalizedLink, $scannedUrls)
                                && !in_array($normalizedLink, $urlsToScan)
                                && !$this->isRecentlyScanned($normalizedLink, $brand->id, 24)
                            ) {
                                $urlsToScan[] = $normalizedLink;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('PageScanner: Failed to scan URL', [
                        'url' => $url,
                        'error' => $e->getMessage(),
                    ]);
                    $scannedUrls[] = $url;
                }
            }
        }

        Log::info('PageScanner: Full scan completed', [
            'brand_id' => $brand->id,
            'pages_scanned' => count($results),
                  'total_urls_found' => count($scannedUrls),
                  'depth' => $depth,
        ]);

        return $results;
    }


    /**
     * Fetch page content.
     */
    protected function fetchPage(string $url): ?array
    {
        $startTime = microtime(true);

        try {
            $response = Http::timeout($this->config['timeout'])
            ->withHeaders([
                'User-Agent' => $this->config['user_agent'],
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ])
            ->get($url);

            $loadTimeMs = round((microtime(true) - $startTime) * 1000, 2);

            if (!$response->successful()) {
                throw new \Exception("HTTP {$response->status()}: " . $response->body());
            }

            return [
                'html' => $response->body(),
                'load_time_ms' => $loadTimeMs,
                'status_code' => $response->status(),
                'content_type' => $response->header('Content-Type'),
            ];

        } catch (\Exception $e) {
            Log::warning('Failed to fetch page', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Parse HTML and extract structured data.
     */
    protected function parseHtml(string $html, string $url): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        $title = $this->extractTitle($dom, $xpath);
        $headings = $this->extractHeadings($dom, $xpath);
        $content = $this->extractContent($dom, $xpath);
        $metaTags = $this->extractMetaTags($dom, $xpath);
        $links = $this->extractLinks($dom, $xpath, $url);
        $images = $this->extractImages($dom, $xpath, $url);
        $pageType = $this->detectPageType($url, $title, $content, $metaTags);

        return [
            'title' => $title,
            'headings' => $headings,
            'content' => $content,
            'word_count' => str_word_count(strip_tags($content)),
            'readability_score' => $this->calculateReadability($content),
            'meta_title' => $metaTags['title'] ?? $title,
            'meta_description' => $metaTags['description'] ?? null,
            'meta_keywords' => $metaTags['keywords'] ?? null,
            'canonical_url' => $metaTags['canonical'] ?? null,
            'og_tags' => $metaTags['og_tags'] ?? null,
            'schema_markup' => $metaTags['schema'] ?? null,
            'internal_links' => $links['internal'],
            'external_links' => $links['external'],
            'broken_links' => [],
            'image_count' => count($images),
            'images' => $images,
            'page_type' => $pageType,
        ];
    }

    protected function extractTitle(DOMDocument $dom, DOMXPath $xpath): ?string
    {
        $titleNodes = $xpath->query('//title');
        return $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : null;
    }

    protected function extractHeadings(DOMDocument $dom, DOMXPath $xpath): array
    {
        $headings = ['h1' => null, 'h2' => [], 'h3' => [], 'h4' => []];

        $h1Nodes = $xpath->query('//h1');
        if ($h1Nodes->length > 0) {
            $headings['h1'] = trim($h1Nodes->item(0)->textContent);
        }

        foreach (['h2', 'h3', 'h4'] as $tag) {
            $nodes = $xpath->query("//{$tag}");
            foreach ($nodes as $node) {
                $headings[$tag][] = trim($node->textContent);
            }
        }

        return $headings;
    }

    protected function extractContent(DOMDocument $dom, DOMXPath $xpath): string
    {
        $selectors = [
            '//article',
            '//main',
            '//div[@class="content" or @id="content" or @class="main" or @id="main"]',
            '//section[@class="content" or @id="content"]',
            '//body',
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $content = '';
                foreach ($nodes as $node) {
                    $content .= $dom->saveHTML($node);
                }
                if (strlen(strip_tags($content)) > 100) {
                    return $content;
                }
            }
        }

        $bodyNodes = $xpath->query('//body');
        return $bodyNodes->length > 0 ? $dom->saveHTML($bodyNodes->item(0)) : $dom->saveHTML();
    }

    protected function extractMetaTags(DOMDocument $dom, DOMXPath $xpath): array
    {
        $metaTags = [
            'title' => null,
            'description' => null,
            'keywords' => null,
            'canonical' => null,
            'og_tags' => [],
            'schema' => null,
        ];

        $metaNodes = $xpath->query('//meta');
        foreach ($metaNodes as $node) {
            $name = strtolower($node->getAttribute('name'));
            $property = strtolower($node->getAttribute('property'));
            $content = $node->getAttribute('content');

            if ($name === 'description') {
                $metaTags['description'] = $content;
            } elseif ($name === 'keywords') {
                $metaTags['keywords'] = $content;
            } elseif (str_starts_with($property, 'og:')) {
                $key = str_replace('og:', '', $property);
                $metaTags['og_tags'][$key] = $content;
            }
        }

        $canonicalNodes = $xpath->query('//link[@rel="canonical"]');
        if ($canonicalNodes->length > 0) {
            $metaTags['canonical'] = $canonicalNodes->item(0)->getAttribute('href');
        }

        $schemaNodes = $xpath->query('//script[@type="application/ld+json"]');
        if ($schemaNodes->length > 0) {
            $metaTags['schema'] = $schemaNodes->item(0)->textContent;
        }

        return $metaTags;
    }

    protected function extractLinks(DOMDocument $dom, DOMXPath $xpath, string $baseUrl): array
    {
        $internal = [];
        $external = [];
        $baseDomain = parse_url($baseUrl, PHP_URL_HOST);

        $linkNodes = $xpath->query('//a[@href]');
        foreach ($linkNodes as $node) {
            $href = $node->getAttribute('href');
            if (empty($href) || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            $fullUrl = $this->resolveUrl($href, $baseUrl);
            if (!$fullUrl) {
                continue;
            }

            $linkData = [
                'url' => $fullUrl,
                'text' => trim($node->textContent),
                'title' => $node->getAttribute('title'),
                'rel' => $node->getAttribute('rel'),
                'target' => $node->getAttribute('target'),
            ];

            $linkDomain = parse_url($fullUrl, PHP_URL_HOST);
            if ($linkDomain === $baseDomain) {
                $internal[] = $linkData;
            } else {
                $external[] = $linkData;
            }

            if (count($internal) + count($external) >= $this->config['max_links']) {
                break;
            }
        }

        return [
            'internal' => array_slice($internal, 0, $this->config['max_links']),
            'external' => array_slice($external, 0, $this->config['max_links']),
        ];
    }

    protected function extractImages(DOMDocument $dom, DOMXPath $xpath, string $baseUrl): array
    {
        $images = [];
        $imgNodes = $xpath->query('//img[@src]');

        foreach ($imgNodes as $node) {
            $src = $node->getAttribute('src');
            if (empty($src)) {
                continue;
            }

            $fullUrl = $this->resolveUrl($src, $baseUrl);
            if (!$fullUrl) {
                continue;
            }

            $images[] = [
                'url' => $fullUrl,
                'alt' => $node->getAttribute('alt'),
                'width' => $node->getAttribute('width'),
                'height' => $node->getAttribute('height'),
                'title' => $node->getAttribute('title'),
            ];

            if (count($images) >= 50) {
                break;
            }
        }

        return $images;
    }

    protected function detectPageType(string $url, ?string $title, string $content, array $metaTags): string
    {
        $urlLower = strtolower($url);
        $titleLower = strtolower($title ?? '');
        $contentLower = strtolower(strip_tags($content));

        if (Str::contains($urlLower, ['/blog/', '/post/', '/article/'])) {
            return 'blog';
        }
        if (Str::contains($urlLower, ['/contact', '/kontakt'])) {
            return 'contact';
        }
        if (Str::contains($urlLower, ['/about', '/uber'])) {
            return 'about';
        }
        if (Str::contains($urlLower, ['/service', '/leistung', '/product'])) {
            return 'service';
        }

        $path = parse_url($urlLower, PHP_URL_PATH);
        if ($path === '/' || empty($path) || $path === '/home') {
            return 'home';
        }

        if (Str::contains($titleLower, ['blog', 'post'])) {
            return 'blog';
        }
        if (Str::contains($titleLower, 'contact')) {
            return 'contact';
        }
        if (Str::contains($titleLower, ['about', 'about us'])) {
            return 'about';
        }

        $serviceKeywords = ['service', 'consulting', 'safari', 'tour', 'package', 'offer', 'pricing'];
        foreach ($serviceKeywords as $keyword) {
            if (str_contains($contentLower, $keyword) && str_contains($titleLower, $keyword)) {
                return 'service';
            }
        }

        return 'other';
    }

    /**
     * Calculate Flesch Reading Ease score with division-by-zero protection.
     */
    protected function calculateReadability(string $content): float
    {
        $text = strip_tags($content);
        $words = str_word_count($text);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentenceCount = count($sentences);

        if ($words === 0 || $sentenceCount === 0) {
            return 0.0;
        }

        $syllableCount = $this->countSyllables($text);

        // Standard Flesch Reading Ease formula
        $score = 206.835 - (1.015 * ($words / $sentenceCount)) - (84.6 * ($syllableCount / $words));

        return max(0.0, min(100.0, round($score, 2)));
    }

    /**
     * Approximates total syllables in a body of text.
     */
    protected function countSyllables(string $text): int
    {
        $words = str_word_count(strtolower($text), 1);
        $totalSyllables = 0;

        foreach ($words as $word) {
            $word = preg_replace('/[^a-z]/', '', $word);
            if (empty($word)) {
                continue;
            }
            // Basic English syllable heuristic
            $syllables = preg_match_all('/[aeiouy]{1,2}/i', $word);
            if (str_ends_with($word, 'e') && !str_ends_with($word, 'le') && $syllables > 1) {
                $syllables--;
            }
            $totalSyllables += max(1, $syllables);
        }

        return $totalSyllables;
    }

    protected function analyzeContent(array $parsedData): array
    {
        $content = strip_tags($parsedData['content'] ?? '');
        $words = str_word_count($content);
        $topics = $this->extractTopics($content);

        return [
            'topics_covered' => $topics,
            'word_count' => $words,
        ];
    }

    protected function extractTopics(string $content): array
    {
        $stopWords = ['the', 'and', 'for', 'with', 'this', 'that', 'from', 'have', 'are', 'was', 'were', 'will', 'would', 'could', 'should', 'can', 'may', 'might', 'must', 'shall', 'about', 'your', 'their'];
        $words = str_word_count(strtolower($content), 1);
        $filtered = array_diff($words, $stopWords);

        $frequencies = array_count_values($filtered);
        arsort($frequencies);

        return array_keys(array_slice($frequencies, 0, 20));
    }

    protected function identifyGaps(array $analysis, ?AiAction $action): array
    {
        $gaps = [];
        $wordCount = $analysis['word_count'] ?? 0;

        if ($wordCount < 300) {
            $gaps[] = 'Content is very short. Consider expanding to at least 500 words.';
        } elseif ($wordCount < 500) {
            $gaps[] = 'Content could be expanded. Consider adding more detail.';
        }

        if ($action && $action->target_keyword) {
            $topics = $analysis['topics_covered'] ?? [];
            $keyword = strtolower($action->target_keyword);

            if (!in_array($keyword, $topics)) {
                $gaps[] = "Target keyword '{$action->target_keyword}' is not found in the content.";
            }
        }

        return $gaps;
    }

    protected function generateRecommendations(array $analysis, array $gaps, ?AiAction $action): array
    {
        $recommendations = [];
        $wordCount = $analysis['word_count'] ?? 0;

        if ($wordCount < 300) {
            $recommendations[] = 'Expand content to at least 500 words for better SEO performance.';
        } elseif ($wordCount < 500) {
            $recommendations[] = 'Consider adding more detailed information to reach 800+ words.';
        } elseif ($wordCount < 1000) {
            $recommendations[] = 'Good length. Consider adding more examples or case studies.';
        }

        if ($action && $action->target_keyword) {
            $recommendations[] = "Optimize content for target keyword: '{$action->target_keyword}'.";
            $recommendations[] = "Include internal links to relevant pages using the keyword as anchor text.";
        }

        if ($action) {
            $recommendations[] = "Review the action: {$action->title}";
            $recommendations[] = "Focus on: {$action->description}";
        }

        return array_slice($recommendations, 0, 10);
    }

    /**
     * Resolves relative URLs correctly against base path.
     */
    protected function resolveUrl(string $url, string $baseUrl): ?string
    {
        if (empty($url)) {
            return null;
        }

        // Trim whitespace
        $url = trim($url);

        // If already absolute, return without anchor
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $this->stripAnchor($url);
        }

        // Parse the base URL
        $baseParts = parse_url($baseUrl);
        $scheme = $baseParts['scheme'] ?? 'https';
        $host = $baseParts['host'] ?? '';

        if (empty($host)) {
            Log::warning('resolveUrl: Invalid base URL', ['baseUrl' => $baseUrl]);
            return null;
        }

        // Protocol-relative URL (starts with //)
        if (str_starts_with($url, '//')) {
            return $this->stripAnchor($scheme . ':' . $url);
        }

        // Absolute path (starts with /)
        if (str_starts_with($url, '/')) {
            return $this->stripAnchor($scheme . '://' . $host . $url);
        }

        // Relative path - build from base path
        $basePath = $baseParts['path'] ?? '/';

        // If base path is a file (has extension), get its directory
        if (pathinfo($basePath, PATHINFO_EXTENSION)) {
            $basePath = dirname($basePath);
        }

        // Ensure base path ends with /
        if (!str_ends_with($basePath, '/')) {
            $basePath .= '/';
        }

        // Build the full URL
        $fullUrl = $scheme . '://' . $host . $basePath . $url;

        // Clean up any double slashes in the path (but not in the protocol)
        $fullUrl = preg_replace('/(?<!:)\/+/', '/', $fullUrl);

        return $this->stripAnchor($fullUrl);
    }

    /**
     * Strip anchor (#) from URL.
     */
    protected function stripAnchor(string $url): string
    {
        return strtok($url, '#');
    }

    /**
     * Get directory path from a URL path.
     */
    protected function getDirectory(string $path): string
    {
        if (substr($path, -1) === '/') {
            return $path;
        }
        $parts = explode('/', $path);
        array_pop($parts);
        return implode('/', $parts) . '/';
    }

    /**
     * Normalizes URLs for accurate deduplication.
     */
    protected function normalizeUrl(string $url): string
    {
        // Remove anchor
        $url = $this->stripAnchor($url);

        // Remove trailing slash
        $url = rtrim($url, '/');

        // Ensure HTTPS
        if (str_starts_with($url, 'http://')) {
            $url = str_replace('http://', 'https://', $url);
        }

        // Ensure www is consistent (remove www for deduplication)
        $url = str_replace('://www.', '://', $url);

        // Remove default ports
        $url = str_replace(':80/', '/', $url);
        $url = str_replace(':443/', '/', $url);

        return $url;
    }

}
