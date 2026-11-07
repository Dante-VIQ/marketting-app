<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Persona;
use Illuminate\Console\Command;

/**
 * Adds the first working buyer persona for Vumbi Ventures.
 *
 * It is a hypothesis from the brand's positioning, not research: no quote and
 * no evidence are invented. Add real evidence on the Personas page as it comes in
 * (ad age/gender, GA4 country split, affiliate-click pages, comments).
 */
class SeedVumbiPersona extends Command
{
    protected $signature = 'personas:seed-vumbi {--brand=Vumbi Ventures : Brand name}';
    protected $description = 'Add the first-time East Africa trip planner persona (hypothesis) for Vumbi Ventures';

    public const NAME = 'First-time East Africa trip planner (hypothesis)';

    public function handle(): int
    {
        $brand = Brand::where('name', $this->option('brand'))->first();
        if (! $brand) {
            $this->error('Brand "' . $this->option('brand') . '" not found.');
            return self::FAILURE;
        }

        $persona = Persona::firstOrCreate(
            ['brand_id' => $brand->id, 'name' => self::NAME],
            [
                'role_title' => 'Aged 28-45; US, UK, Europe or African diaspora; planning a first safari or trek within 3-12 months',
                'summary' => 'Researching a high-ticket, first-time trip to East Africa (Kenya, Tanzania, Rwanda, Uganda) and wants honest, practical information plus a booking they can trust. Hypothesis from brand positioning, not yet validated with data.',
                'goals' => [
                    'Choose a destination and season with confidence',
                    'Know the real cost and what is included',
                    'Book a trustworthy tour without friction',
                ],
                'triggers' => [
                    'A bucket-list trip or milestone (honeymoon, birthday, heritage visit)',
                    'Seeing a destination on social media or in a travel story',
                    'Searches such as "Kenya first safari", "Bwindi gorilla trekking", "Serengeti when to go"',
                ],
                'pains' => [
                    'Fear of choosing the wrong operator or being scammed',
                    'Unclear total cost and hidden extras',
                    'Safety and health worries (security, malaria, permits)',
                    'Too much generic, hype-driven advice',
                ],
                'objections' => [
                    'Is it safe?',
                    'Is it worth the price?',
                    'Can I trust this operator or site?',
                ],
                'motivations' => [
                    'Authentic experience over tourist-trap trips',
                    'Connection to African culture and history',
                    'Wildlife and once-in-a-lifetime moments',
                ],
                'channels' => [
                    'Google search',
                    'Travel blogs and destination guides',
                    'TikTok and Instagram',
                    'YouTube travel videos',
                ],
                'buying_role' => 'decision_maker',
                'quote' => null,
                'is_active' => true,
            ]
        );

        $this->info($persona->wasRecentlyCreated
            ? "Persona added for {$brand->name}."
            : "Persona already exists for {$brand->name}; nothing changed.");

        return self::SUCCESS;
    }
}
