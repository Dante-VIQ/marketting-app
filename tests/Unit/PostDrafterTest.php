<?php

namespace Tests\Unit;

use App\Models\ContentIdea;
use App\Services\ContentPlan\PostDrafter;
use App\Services\Meta\ContentPublisher;
use App\Services\Meta\MetaAssets;
use App\Services\Meta\MetaPublisher;
use Illuminate\Support\Carbon;
use Tests\Unit\Support\MetaTestCase;

class PostDrafterTest extends MetaTestCase
{
    public function test_prompt_forbids_inventing_facts_and_carries_only_given_facts(): void
    {
        $p = PostDrafter::buildPrompt('Vumbi Ventures', ['voice' => 'warm, practical'], ['name' => 'Planner'], ['title' => 'Best time for the Mara', 'platform' => 'facebook', 'format' => 'image']);
        $this->assertStringContainsString('Never invent prices', $p['system_prompt']);
        $this->assertStringContainsString('NO text or logos', $p['system_prompt']);
        $this->assertStringContainsString('warm, practical', $p['user_prompt']);
        $this->assertStringContainsString('no measured posts yet', $p['user_prompt']);
    }

    public function test_parse_reads_plain_fenced_and_wrapped_json(): void
    {
        $json = '{"copy":"Hello","image_prompt":"A savanna at dawn","image_alt":"Savanna","reasoning":"x"}';
        foreach ([$json, "```json\n$json\n```", "Sure! $json Thanks"] as $raw) {
            $this->assertSame('A savanna at dawn', PostDrafter::parse($raw)['image_prompt']);
        }
    }

    public function test_parse_rejects_unusable_answers_and_blank_image_prompt_is_null(): void
    {
        $this->assertNull(PostDrafter::parse('not json'));
        $this->assertNull(PostDrafter::parse('{"copy":"  "}'));
        $this->assertNull(PostDrafter::parse('{"image_prompt":"x"}'));
        $this->assertNull(PostDrafter::parse('{"copy":"Hi","image_prompt":"  "}')['image_prompt']);
    }

    public function test_needs_image_for_visual_formats_and_instagram(): void
    {
        $this->assertTrue(PostDrafter::needsImage(new ContentIdea(['format' => 'carousel'])));
        $this->assertTrue(PostDrafter::needsImage(new ContentIdea(['platform' => 'instagram'])));
        $this->assertFalse(PostDrafter::needsImage(new ContentIdea(['platform' => 'facebook', 'format' => 'text'])));
    }

    public function test_links_get_utm_tags_without_overwriting_existing_ones(): void
    {
        $idea = new ContentIdea(['platform' => 'facebook']);
        $idea->id = 42;
        $this->assertSame('https://vumbiventures.com/safari?utm_source=facebook&utm_medium=social&utm_campaign=vumbiai-42', ContentPublisher::tagLink('https://vumbiventures.com/safari', $idea));
        $this->assertSame('https://x.com/a?ref=1&utm_source=facebook&utm_medium=social&utm_campaign=vumbiai-42#top', ContentPublisher::tagLink('https://x.com/a?ref=1#top', $idea));
        $this->assertStringContainsString('utm_source=newsletter', ContentPublisher::tagLink('https://x.com/?utm_source=newsletter', $idea));
        $this->assertNull(ContentPublisher::tagLink(null, $idea));
    }

    public function test_image_post_cannot_be_approved_without_its_image(): void
    {
        $g = $this->graph();
        $pub = new ContentPublisher(new MetaAssets($g), new MetaPublisher($g), fn () => []);
        $idea = ContentIdea::create(['brand_id' => 1, 'title' => 't', 'platform' => 'facebook', 'format' => 'image', 'copy' => 'Hi', 'status' => 'ready']);
        $this->expectExceptionMessage('needs its image');
        $pub->approve($idea, 7, Carbon::now()->addHour(), null, null);
    }

    public function test_approved_link_is_stored_tagged(): void
    {
        $g = $this->graph();
        $pub = new ContentPublisher(new MetaAssets($g), new MetaPublisher($g), fn () => []);
        $idea = ContentIdea::create(['brand_id' => 1, 'title' => 't', 'platform' => 'facebook', 'copy' => 'Hi', 'status' => 'ready']);
        $pub->approve($idea, 7, Carbon::now()->addHour(), null, 'https://vumbiventures.com/x');
        $this->assertStringContainsString('utm_campaign=vumbiai-' . $idea->id, $idea->fresh()->link_url);
    }
}
