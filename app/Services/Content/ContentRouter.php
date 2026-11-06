<?php

namespace App\Services\Content;

use App\Models\BlogPost;
use App\Models\ContentDraft;
use App\Models\TravelGuide;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Routes an approved ContentDraft to its destination section of the site.
 *
 * Type → Destination:
 *   blog        → BlogPost (travel articles, field notes, history & culture)
 *   travel_guide → TravelGuide (destination guides with tour packages)
 *   web_copy    → BlogPost with category "web_copy" (landing page copy staged for review)
 *   social      → ContentIdea / Meta queue (handled separately by ContentPublisher)
 *   email       → Stored as published draft only (no internal email CMS yet)
 *   newsletter  → Stored as published draft only (no internal newsletter CMS yet)
 *
 * Usage:
 *   $router = new ContentRouter();
 *   $result = $router->route($draft);
 *   // $result['destination'] — 'blog_post' | 'travel_guide' | 'draft_only'
 *   // $result['id']          — the created/updated record id (when applicable)
 *   // $result['url']         — the internal edit/view URL (when applicable)
 */
class ContentRouter
{
    /**
     * Route the draft to its destination.
     * Returns metadata about where it landed.
     *
     * @return array{destination: string, id: int|null, url: string|null, note: string}
     */
    public function route(ContentDraft $draft): array
    {
        return match ($draft->type) {
            'blog'       => $this->routeToBlog($draft),
            'travel_guide' => $this->routeToTravelGuide($draft),
            'web_copy'   => $this->routeToWebCopy($draft),
            'social'     => $this->draftOnly($draft, 'Social posts are published via the Content Plan → Schedule flow.'),
            'email',
            'newsletter' => $this->draftOnly($draft, ucfirst($draft->type) . ' drafts are marked published — send via your email platform.'),
            default      => $this->draftOnly($draft, 'No automatic routing for type "' . $draft->type . '".'),
        };
    }

    // ── Destinations ──────────────────────────────────────────────────────────

    private function routeToBlog(ContentDraft $draft): array
    {
        $slug   = $this->makeSlug($draft->title, 'blog_posts');
        $source = $draft->action_id ? 'ai_action' : 'ai_draft';

        $post = BlogPost::create([
            'brand_id'         => $draft->brand_id,
            'title'            => $draft->title,
            'slug'             => $slug,
            'content'          => $draft->content,
            'excerpt'          => $draft->excerpt ?? $this->autoExcerpt($draft->content),
            'meta_title'       => $draft->meta_title ?? $draft->title,
            'meta_description' => $draft->meta_description,
            'seo_data'         => $draft->seo_data,
            'author'           => 'Revora AI',
            'status'           => 'published',
            'source'           => $source,
            'action_id'        => $draft->action_id,
            'published_at'     => now(),
            'last_updated_at'  => now(),
            'metadata'         => array_merge($draft->metadata ?? [], [
                'content_draft_id' => $draft->id,
                'routed_at'        => now()->toDateTimeString(),
            ]),
        ]);

        // Write back the published URL so the draft links to it
        $draft->update([
            'published_url' => route('blog.show', $post->id),
        ]);

        Log::info('ContentRouter: draft routed to BlogPost', [
            'draft_id'     => $draft->id,
            'blog_post_id' => $post->id,
            'slug'         => $slug,
        ]);

        return [
            'destination' => 'blog_post',
            'id'          => $post->id,
            'url'         => route('blog.show', $post->id),
            'note'        => 'Published to the blog as "' . $draft->title . '".',
        ];
    }

    private function routeToTravelGuide(ContentDraft $draft): array
    {
        $slug      = $this->makeSlug($draft->title, 'travel_guides');
        $metadata  = $draft->metadata ?? [];

        $guide = TravelGuide::create([
            'brand_id'    => $draft->brand_id,
            'title'       => $draft->title,
            'slug'        => $slug,
            'destination' => $metadata['destination'] ?? $this->inferDestination($draft->title),
            'description' => $draft->excerpt ?? $this->autoExcerpt($draft->content),
            'content'     => $draft->content,
            'status'      => 'published',
            'published_at' => now(),
            'metadata'    => array_merge($metadata, [
                'content_draft_id' => $draft->id,
                'routed_at'        => now()->toDateTimeString(),
            ]),
        ]);

        // No named route for travel guides yet — store a path-based URL
        $guideUrl = '/travel-guides/' . $slug;
        $draft->update([
            'published_url' => $guideUrl,
        ]);

        Log::info('ContentRouter: draft routed to TravelGuide', [
            'draft_id'        => $draft->id,
            'travel_guide_id' => $guide->id,
            'slug'            => $slug,
        ]);

        return [
            'destination' => 'travel_guide',
            'id'          => $guide->id,
            'url'         => $guideUrl,
            'note'        => 'Published to Travel Guides as "' . $draft->title . '".',
        ];
    }

    private function routeToWebCopy(ContentDraft $draft): array
    {
        // Web copy goes into BlogPost with a "web_copy" category so it's staged
        // for the site owner to place, but searchable in the blog section.
        $slug = $this->makeSlug($draft->title, 'blog_posts');

        $post = BlogPost::create([
            'brand_id'         => $draft->brand_id,
            'title'            => $draft->title,
            'slug'             => $slug,
            'content'          => $draft->content,
            'excerpt'          => $draft->excerpt ?? $this->autoExcerpt($draft->content),
            'meta_title'       => $draft->meta_title ?? $draft->title,
            'meta_description' => $draft->meta_description,
            'categories'       => ['web_copy'],
            'status'           => 'draft', // staged, not live — owner places it manually
            'source'           => 'ai_draft',
            'action_id'        => $draft->action_id,
            'metadata'         => array_merge($draft->metadata ?? [], [
                'content_draft_id' => $draft->id,
                'routed_at'        => now()->toDateTimeString(),
                'web_copy_note'    => 'Staged for placement — not yet live on a page.',
            ]),
        ]);

        Log::info('ContentRouter: web copy staged in BlogPost (draft)', [
            'draft_id'     => $draft->id,
            'blog_post_id' => $post->id,
        ]);

        return [
            'destination' => 'blog_post',
            'id'          => $post->id,
            'url'         => null,
            'note'        => 'Staged as a draft blog post — place it on the page manually when ready.',
        ];
    }

    private function draftOnly(ContentDraft $draft, string $note): array
    {
        return [
            'destination' => 'draft_only',
            'id'          => null,
            'url'         => null,
            'note'        => $note,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Generate a unique slug — appends -2, -3 etc. if the base slug is taken.
     */
    private function makeSlug(string $title, string $table): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i    = 2;

        while (\Illuminate\Support\Facades\DB::table($table)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function autoExcerpt(string $content): string
    {
        return Str::limit(strip_tags($content), 200);
    }

    /**
     * Guess the destination from the title (e.g. "Zanzibar Travel Guide" → "Zanzibar").
     * Falls back to the full title if no match.
     */
    private function inferDestination(string $title): string
    {
        // Strip common suffixes to get the place name
        $clean = preg_replace('/\b(travel guide|destination guide|guide|trip|itinerary)\b/i', '', $title);
        return trim($clean) ?: $title;
    }
}
