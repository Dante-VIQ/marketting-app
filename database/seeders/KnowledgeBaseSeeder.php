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
            [
                'key' => 'brand_voice',
                'category' => 'brand',
                'content' => 'Professional, adventurous, trustworthy, and data-driven. Speak with authority about African travel, technology, and business growth. Avoid jargon and keep tone warm yet professional.',
            ],
            [
                'key' => 'services',
                'category' => 'services',
                'content' => "1. Safari Tours - Luxury and budget safari experiences across Kenya and Tanzania\n2. SEO Consulting - Search engine optimization for travel and tourism businesses\n3. Software Development - Custom web and mobile applications\n4. Digital Marketing - Social media management and content marketing",
            ],
            [
                'key' => 'pricing',
                'category' => 'pricing',
                'content' => "Safari Tours: $500 - $5,000 per person\nSEO Consulting: $1,000 - $5,000 per month\nSoftware Development: $5,000 - $50,000 per project\nDigital Marketing: $500 - $3,000 per month",
            ],
            [
                'key' => 'faqs',
                'category' => 'faqs',
                'content' => "Q: What is the best time for safari in Kenya?\nA: The best time is during the dry season (June to October) when wildlife is easier to spot.\n\nQ: Do you offer custom software solutions?\nA: Yes, we specialize in custom web and mobile applications for businesses.",
            ],
            [
                'key' => 'target_audience',
                'category' => 'audience',
                'content' => "1. Travel enthusiasts looking for authentic African experiences\n2. Safari operators needing digital marketing and SEO\n3. Tourism businesses requiring software solutions\n4. Adventure seekers and luxury travelers",
            ],
            [
                'key' => 'competitors',
                'category' => 'competitors',
                'content' => "1. SafariBookings.com - Direct competitor for safari tours\n2. TripAdvisor - Indirect competitor for travel reviews\n3. Local safari operators - Direct competitors for tours\n4. Digital marketing agencies - Competitors for SEO and marketing services",
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
