<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\BlogPost;
use App\Models\AiAction;
use App\Services\AI\ContentGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    protected ContentGeneratorService $contentGenerator;

    public function __construct(ContentGeneratorService $contentGenerator)
    {
        $this->contentGenerator = $contentGenerator;
    }

    /**
     * Display the blog dashboard.
     */
    public function index()
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        // Get blog posts
        $posts = BlogPost::where('brand_id', $brand->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get statistics
        $totalPosts = $posts->count();
        $publishedPosts = BlogPost::where('brand_id', $brand->id)
            ->where('status', 'published')
            ->count();
        $draftPosts = BlogPost::where('brand_id', $brand->id)
            ->where('status', 'draft')
            ->count();
        $aiGeneratedPosts = BlogPost::where('brand_id', $brand->id)
            ->where('source', 'ai_generated')
            ->count();

        // Get approved actions waiting for content
        $pendingContent = AiAction::where('brand_id', $brand->id)
            ->where('status', 'approved')
            ->whereDoesntHave('contentDraft')
            ->count();

        return view('blog.index', compact(
            'brand',
            'posts',
            'totalPosts',
            'publishedPosts',
            'draftPosts',
            'aiGeneratedPosts',
            'pendingContent'
        ));
    }

    /**
     * Show a specific blog post.
     */
    public function show($id)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $post = BlogPost::where('brand_id', $brand->id)->findOrFail($id);

        return view('blog.show', compact('post'));
    }

/**
 * Show the import page.
 */
public function import()
{
    $user = Auth::user();
    $brand = $user->activeBrand;

    if (!$brand) {
        return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
    }

    return view('blog.import');
}
    /**
     * Import from WordPress REST API.
     */
    public function importWordPress(Request $request)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $request->validate([
            'url' => 'required|url',
        ]);

        $wpUrl = rtrim($request->url, '/');
        $apiUrl = $wpUrl . '/wp-json/wp/v2/posts';

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(60)->get($apiUrl, [
                'per_page' => 100,
                'status' => 'publish',
            ]);

            if (!$response->successful()) {
                return redirect()->route('blog.index')->with('error', 'Failed to fetch WordPress posts. Please check the URL and try again.');
            }

            $posts = $response->json();
            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($posts as $post) {
                $slug = Str::slug($post['slug'] ?? $post['title']['rendered'] ?? 'untitled');

                // Check if post already exists
                if (BlogPost::where('brand_id', $brand->id)->where('slug', $slug)->exists()) {
                    $skipped++;
                    continue;
                }

                try {
                    $content = $post['content']['rendered'] ?? '';
                    $title = $post['title']['rendered'] ?? 'Untitled';
                    $excerpt = $post['excerpt']['rendered'] ?? null;
                    $date = $post['date'] ?? null;

                    BlogPost::create([
                        'brand_id' => $brand->id,
                        'title' => $title,
                        'slug' => $slug,
                        'content' => $content,
                        'excerpt' => strip_tags($excerpt),
                        'author' => $post['author'] ?? null,
                        'status' => 'published',
                        'source' => 'manual',
                        'published_at' => $date ? \Carbon\Carbon::parse($date) : now(),
                        'metadata' => [
                            'imported_from' => 'wordpress',
                            'original_url' => $wpUrl,
                            'original_id' => $post['id'] ?? null,
                        ],
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to import post: " . ($post['title']['rendered'] ?? 'Unknown') . " - " . $e->getMessage();
                }
            }

            $message = "✅ Imported {$imported} posts from WordPress.";
            if ($skipped > 0) {
                $message .= " Skipped {$skipped} (already exist).";
            }
            if (!empty($errors)) {
                $message .= " Errors: " . implode('; ', $errors);
            }

            return redirect()->route('blog.index')->with('message', $message);
        } catch (\Exception $e) {
            Log::error('WordPress import failed', [
                'brand_id' => $brand->id,
                'url' => $wpUrl,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('blog.index')->with('error', 'Failed to import from WordPress: ' . $e->getMessage());
        }
    }

    /**
     * Import from CSV file.
     */
    public function importCsv(Request $request)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        try {
            $file = $request->file('csv_file');
            $handle = fopen($file->getPathname(), 'r');
            
            if (!$handle) {
                return redirect()->route('blog.index')->with('error', 'Could not read CSV file.');
            }

            // Read headers
            $headers = fgetcsv($handle);
            if (!$headers) {
                fclose($handle);
                return redirect()->route('blog.index')->with('error', 'CSV file is empty or invalid.');
            }

            // Normalize headers to lowercase for easier matching
            $normalizedHeaders = array_map('strtolower', $headers);
            
            // Find required columns
            $titleIndex = array_search('title', $normalizedHeaders);
            $contentIndex = array_search('content', $normalizedHeaders);
            $excerptIndex = array_search('excerpt', $normalizedHeaders);
            $dateIndex = array_search('published_at', $normalizedHeaders);
            $slugIndex = array_search('slug', $normalizedHeaders);
            $tagsIndex = array_search('tags', $normalizedHeaders);
            $categoriesIndex = array_search('categories', $normalizedHeaders);

            if ($titleIndex === false && $contentIndex === false) {
                fclose($handle);
                return redirect()->route('blog.index')->with('error', 'CSV must have at least "title" or "content" columns.');
            }

            $imported = 0;
            $skipped = 0;
            $errors = [];

            while (($row = fgetcsv($handle)) !== false) {
                try {
                    $title = $titleIndex !== false ? ($row[$titleIndex] ?? 'Untitled') : 'Untitled';
                    $slug = $slugIndex !== false ? Str::slug($row[$slugIndex] ?? $title) : Str::slug($title);

                    // Check if post already exists
                    if (BlogPost::where('brand_id', $brand->id)->where('slug', $slug)->exists()) {
                        $skipped++;
                        continue;
                    }

                    $content = $contentIndex !== false ? ($row[$contentIndex] ?? '') : '';
                    $excerpt = $excerptIndex !== false ? ($row[$excerptIndex] ?? null) : null;
                    $publishedDate = $dateIndex !== false ? ($row[$dateIndex] ?? null) : null;
                    $tags = $tagsIndex !== false ? explode(',', $row[$tagsIndex] ?? '') : [];
                    $categories = $categoriesIndex !== false ? explode(',', $row[$categoriesIndex] ?? '') : [];

                    BlogPost::create([
                        'brand_id' => $brand->id,
                        'title' => $title,
                        'slug' => $slug,
                        'content' => $content,
                        'excerpt' => $excerpt,
                        'author' => auth()->user()->name,
                        'tags' => $tags,
                        'categories' => $categories,
                        'status' => 'draft',
                        'source' => 'manual',
                        'published_at' => $publishedDate ? \Carbon\Carbon::parse($publishedDate) : null,
                        'metadata' => [
                            'imported_from' => 'csv',
                        ],
                    ]);

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Row error: " . $e->getMessage();
                }
            }

            fclose($handle);

            $message = "✅ Imported {$imported} posts from CSV.";
            if ($skipped > 0) {
                $message .= " Skipped {$skipped} (already exist).";
            }
            if (!empty($errors)) {
                $message .= " Errors: " . implode('; ', $errors);
            }

            return redirect()->route('blog.index')->with('message', $message);
        } catch (\Exception $e) {
            Log::error('CSV import failed', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('blog.index')->with('error', 'Failed to import CSV: ' . $e->getMessage());
        }
    }

    /**
     * Manual import - add a single post.
     */
    public function importManual(Request $request)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        try {
            $slug = Str::slug($request->title);

            // Check if post already exists
            if (BlogPost::where('brand_id', $brand->id)->where('slug', $slug)->exists()) {
                return redirect()->route('blog.index')->with('error', 'A post with this title already exists.');
            }

            BlogPost::create([
                'brand_id' => $brand->id,
                'title' => $request->title,
                'slug' => $slug,
                'content' => $request->content,
                'author' => auth()->user()->name,
                'status' => 'draft',
                'source' => 'manual',
                'published_at' => null,
                'metadata' => [
                    'imported_from' => 'manual',
                ],
            ]);

            return redirect()->route('blog.index')->with('message', '✅ Blog post created successfully.');
        } catch (\Exception $e) {
            Log::error('Manual import failed', [
                'brand_id' => $brand->id,
                'title' => $request->title,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('blog.index')->with('error', 'Failed to create post: ' . $e->getMessage());
        }
    }

    /**
     * Generate content for all approved actions.
     */
    public function generateAll(Request $request)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Brand not found'], 404);
            }
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        // Get all approved actions without drafts
        $actions = AiAction::where('brand_id', $brand->id)
            ->where('status', 'approved')
            ->whereDoesntHave('contentDraft')
            ->get();

        $count = $actions->count();

        if ($count === 0) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No approved actions waiting for content generation.',
                    'generated' => 0,
                ]);
            }
            return redirect()->route('blog.index')->with('message', 'No approved actions waiting for content generation.');
        }

        $generated = 0;
        $failed = 0;
        $errors = [];

        foreach ($actions as $action) {
            try {
                $draft = $this->contentGenerator->generateForAction($action);
                if ($draft) {
                    $generated++;
                } else {
                    $failed++;
                    $errors[] = "Failed to generate content for: " . $action->title;
                }
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Error generating content for '" . $action->title . "': " . $e->getMessage();
                Log::error('Content generation failed', [
                    'action_id' => $action->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = "Generated {$generated} content drafts.";
        if ($failed > 0) {
            $message .= " Failed: {$failed}. Check logs for details.";
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => $generated > 0,
                'message' => $message,
                'generated' => $generated,
                'failed' => $failed,
                'errors' => $errors,
            ]);
        }

        return redirect()->route('blog.index')->with('message', $message);
    }
}