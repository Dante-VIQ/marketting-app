<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\KnowledgeBase;
use Illuminate\Database\Seeder;

class KnowledgeBaseSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::where('slug', 'vumbiventures')->first();

        if (!$brand) {
            $this->command->error('Brand not found. Please run BrandSeeder first.');
            return;
        }

        $knowledge = [
            // Read directly by ContentGeneratorService::getSystemPrompt() —
            // keep this in sync any time Vumbi's positioning changes.
            [
                'key' => 'business_description',
                'category' => 'brand',
                'content' => 'Vumbi Ventures is an Africa-first travel discovery and storytelling platform. We publish long-form destination guides, African history & culture features, and field notes to help travelers plan authentic trips across East Africa and beyond. Revenue comes from affiliate travel bookings (flights, hotels, tours via Awin, TravelPayouts, BonusArrive, GetYourGuide, Orange Adventures) and manual bookings through local partners — Vumbi Ventures is a media and discovery platform, not a marketing, SEO, or software agency.',
            ],
            [
                'key' => 'content_pillars',
                'category' => 'brand',
                'content' => "1. African History & Culture — untold stories, historical figures, cultural traditions and festivals\n2. East Africa Destination Guides — practical, in-depth guides to specific places (cities, parks, landmarks)\n3. Field Notes — first-person travel stories, local culture, on-the-ground observations (e.g. matatu culture, wedding etiquette)",
            ],
            [
                'key' => 'forbidden_topics',
                'category' => 'brand',
                'content' => 'Never write about: small business marketing, SEO consulting or SEO services, software development or web development services, digital marketing agency services, generic business/startup advice, SaaS or tech products, local shop/restaurant/retail marketing, real estate, personal finance or investing, cryptocurrency, health & fitness, parenting. If a topic is not African travel, African history/culture, or a Vumbi Ventures destination/experience, it does not belong on this site.',
            ],

            // Reference material — dumped into the full knowledge_base context,
            // so keep these accurate too even though they aren't individually
            // parsed by name the way the three keys above are.
            [
                'key' => 'brand_voice',
                'category' => 'brand',
                'content' => 'Warm, knowledgeable, and grounded — like a well-traveled local friend, not a tour brochure. Confident about African history and culture without over-explaining. Avoid travel-blog clichés and AI-sounding phrasing; favor specific, sensory detail over generic superlatives.',
            ],
            [
                'key' => 'services',
                'category' => 'services',
                'content' => "1. Destination discovery & storytelling content — East Africa travel guides, history & culture features, field notes\n2. Affiliate-based trip booking — flights, hotels, and tours via partner networks (Awin, TravelPayouts, BonusArrive, GetYourGuide, Orange Adventures)\n3. Manual/local-partner bookings for tours not covered by affiliate networks (e.g. Orange Adventures packages)",
            ],
            [
                'key' => 'pricing',
                'category' => 'pricing',
                'content' => "Vumbi Ventures does not sell fixed-price services — it earns affiliate commissions on bookings made through partner links, plus referral fees on manually-brokered local-partner tours. Tour/package pricing varies by operator and shows on the relevant tour page, not as a Vumbi rate card.",
            ],
            [
                'key' => 'faqs',
                'category' => 'faqs',
                'content' => "Q: What is the best time for safari in Kenya?\nA: The best time is during the dry season (June to October) when wildlife is easier to spot.\n\nQ: Does Vumbi Ventures sell its own tours?\nA: Most bookings run through affiliate partners (Awin, TravelPayouts, GetYourGuide, BonusArrive) or are manually brokered with local partners like Orange Adventures — Vumbi Ventures is a discovery and booking platform, not a tour operator itself.",
            ],
            [
                'key' => 'target_audience',
                'category' => 'audience',
                'content' => "International travelers interested in authentic African experiences — East Africa destination planning, African history & culture, and first-person field notes from the region. Skews toward independent and culturally-curious travelers rather than package-tour buyers.",
            ],
            [
                'key' => 'competitors',
                'category' => 'competitors',
                'content' => "1. SafariBookings.com - direct competitor for safari tour discovery\n2. TripAdvisor - indirect competitor for travel reviews and planning\n3. Local safari operators' own sites - direct competitors for bookings\n4. Other Africa-focused travel and culture blogs - competitors for content/SEO visibility",
            ],
        ];

        foreach ($knowledge as $item) {
            KnowledgeBase::updateOrCreate(
                [
                    'brand_id' => $brand->id,
                    'key' => $item['key'],
                ],
                [
                    'category' => $item['category'],
                    'content' => $item['content'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Knowledge base seeded successfully!');
    }
}