<?php

namespace App\Services\ContentPlan;

use App\Models\Brand;
use App\Models\ContentIdea;
use App\Models\KnowledgeBase;
use App\Models\Persona;
use App\Services\AI\AiGatewayService;
use DomainException;

/**
 * Drafts the post text and, when the post needs a picture, a prompt for an image tool.
 * AI proposes, a human reviews: nothing here posts or approves anything.
 * It may only use facts the brand actually gave us; it must not invent prices, dates, offers or claims.
 */
class PostDrafter
{
    public function __construct(private ?AiGatewayService $ai = null) {}

    /** Facts the system really holds about the brand. Empty = we do not understand the brand yet. */
    public static function brandFacts(Brand $brand, int $max = 15): array
    {
        $facts = [];
        if (trim((string) $brand->brand_voice) !== '') {
            $facts['voice'] = mb_substr(trim((string) $brand->brand_voice), 0, 1200);
        }
        $kb = KnowledgeBase::where('brand_id', $brand->id)->where('is_active', true)->limit($max)->get(['key', 'content']);
        foreach ($kb as $row) {
            $facts['knowledge'][] = ['topic' => $row->key, 'fact' => mb_substr(trim((string) $row->content), 0, 400)];
        }

        return $facts;
    }

    public static function needsImage(ContentIdea $idea): bool
    {
        return in_array($idea->format, ['image', 'carousel', 'story'], true) || $idea->platform === 'instagram';
    }

    public static function buildPrompt(string $brandName, array $facts, ?array $persona, array $idea, array $topPosts = []): array
    {
        $system = <<<TXT
You write social posts for the brand "{$brandName}". Return ONLY valid JSON.
RULES:
- Use ONLY the brand facts provided. Never invent prices, dates, offers, itineraries, awards, statistics, testimonials or guarantees. If a detail is not in the facts, leave it out.
- If the facts include a "forbidden_topics" entry, never write about anything it lists. If the idea falls under it, return {"copy": "", "image_prompt": null, "image_alt": null, "reasoning": "why it is off-brand"}.
- Match the brand voice. Be specific and useful for the persona; no clickbait, no hashtags spam (max 3 relevant hashtags).
- End with one clear, low-pressure call to action that fits the goal (read, ask a question, plan a trip). No fake urgency.
- "image_prompt": only if the post needs a picture; otherwise null. Describe a realistic photographic scene for an image generator: subject, setting, light, composition, mood, style. NO text or logos inside the image, NO real people or named individuals, NO recognisable trademarks. It must not show anything the post text could be mistaken as promising.
- "image_alt": one plain sentence describing the image for accessibility (null if no image).
JSON shape: {"copy": string, "image_prompt": string|null, "image_alt": string|null, "reasoning": string}
TXT;
        $user = json_encode([
            'platform' => $idea['platform'] ?? null, 'format' => $idea['format'] ?? null,
            'idea' => ['title' => $idea['title'] ?? '', 'topic' => $idea['topic'] ?? null, 'why_we_suggest_it' => $idea['source_detail'] ?? null],
            'persona' => $persona, 'brand_facts' => $facts,
            'what_worked_before' => $topPosts ?: 'no measured posts yet',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return ['system_prompt' => $system, 'user_prompt' => $user, 'temperature' => 0.6, 'max_tokens' => 1200, 'response_format' => 'json'];
    }

    /** @return array{copy:string,image_prompt:?string,image_alt:?string}|null */
    public static function parse(string $content): ?array
    {
        $c = trim($content);
        $c = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $c);
        $d = json_decode($c, true);
        if (!is_array($d) && preg_match('/\{[\s\S]*\}/', $c, $m)) {
            $d = json_decode($m[0], true);
        }
        if (!is_array($d) || !is_string($d['copy'] ?? null) || trim($d['copy']) === '') {
            return null;
        }
        $str = fn ($v, $n) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $n) : null;

        return ['copy' => mb_substr(trim($d['copy']), 0, 5000), 'image_prompt' => $str($d['image_prompt'] ?? null, 1500), 'image_alt' => $str($d['image_alt'] ?? null, 300)];
    }

    /** Fills the idea's text and image prompt (draft only). Throws DomainException with a plain reason on failure. */
    public function draft(ContentIdea $idea, Brand $brand): ContentIdea
    {
        $facts = self::brandFacts($brand);
        if (!$facts) {
            throw new DomainException('Tell the system about the brand first (brand voice or knowledge base entries), so it does not have to guess.');
        }
        $persona = $idea->persona_id ? Persona::where('brand_id', $brand->id)->find($idea->persona_id) : null;
        $personaArr = $persona ? ['name' => $persona->name, 'goals' => $persona->goals, 'pains' => $persona->pains, 'objections' => $persona->objections] : null;
        $top = ContentPerformance::topPosts(ContentIdea::where('brand_id', $brand->id)->where('status', 'posted')->get()->map(fn ($i) => [
            'title' => $i->title, 'platform' => $i->platform, 'format' => $i->format, 'topic' => $i->topic,
            'posted_at' => $i->posted_at?->toDateTimeString(), 'impressions' => $i->impressions, 'engagements' => $i->engagements])->all());

        $res = ($this->ai ?? new AiGatewayService())->generate(self::buildPrompt($brand->name, $facts, $personaArr, $idea->only(['title', 'topic', 'platform', 'format', 'source_detail']), $top));
        if (empty($res['success'])) {
            throw new DomainException('The AI provider did not answer. Nothing was changed; try again shortly.');
        }
        $out = self::parse((string) $res['content']);
        if (!$out) {
            throw new DomainException('The AI answer could not be read. Nothing was changed; try again.');
        }

        $idea->update(['copy' => $out['copy'], 'image_prompt' => self::needsImage($idea) || $out['image_prompt'] ? $out['image_prompt'] : null,
            'image_alt' => $out['image_alt']]);

        return $idea;
    }
}
