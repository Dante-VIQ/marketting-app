<?php

namespace App\Livewire;

use App\Models\Brand;
use App\Services\BrandManagementService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * 3-step brand onboarding wizard.
 * Shown on the brands index when the user has no brands yet.
 */
class BrandOnboarding extends Component
{
    public int $step = 1;

    // Step 1 — Identity
    public string $name        = '';
    public string $website_url = '';
    public string $domain_type = 'marketing';
    public string $timezone    = 'Africa/Nairobi';

    // Step 2 — Audience
    public string $brand_voice         = '';
    public string $target_audience     = '';
    public string $key_differentiator  = '';

    // Step 3 — First goal
    public string $first_goal = 'grow_social';

    protected function rules(): array
    {
        return match ($this->step) {
            1 => [
                'name'        => 'required|string|max:100|unique:brands,name',
                'website_url' => 'nullable|url|max:255',
                'domain_type' => 'required|in:' . implode(',', array_keys(config('brand.domain_types'))),
                'timezone'    => 'required|string',
            ],
            2 => [
                'brand_voice'        => 'required|string|min:20|max:500',
                'target_audience'    => 'required|string|min:10|max:300',
                'key_differentiator' => 'required|string|min:10|max:300',
            ],
            3 => [
                'first_goal' => 'required|in:grow_social,increase_traffic,get_leads,beat_competitors',
            ],
            default => [],
        };
    }

    protected array $messages = [
        'name.unique'              => 'A brand with this name already exists.',
        'brand_voice.min'          => 'Please describe your brand voice in at least 20 characters.',
        'target_audience.min'      => 'Tell us a bit more about your audience (min 10 characters).',
        'key_differentiator.min'   => 'Describe what sets you apart (min 10 characters).',
    ];

    public function nextStep(): void
    {
        $this->validate();
        $this->step++;
    }

    public function prevStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function finish(BrandManagementService $brandService): void
    {
        $this->validate();

        // Build an enriched brand_voice string from audience + differentiator context
        $enrichedVoice = trim($this->brand_voice);
        if ($this->target_audience) {
            $enrichedVoice .= "\n\nTarget audience: " . trim($this->target_audience);
        }
        if ($this->key_differentiator) {
            $enrichedVoice .= "\n\nKey differentiator: " . trim($this->key_differentiator);
        }

        // Store first_goal as a config flag so the AI brief can be informed
        $config = [
            'onboarding_first_goal' => $this->first_goal,
        ];

        $brand = $brandService->createBrand([
            'name'        => trim($this->name),
            'domain_type' => $this->domain_type,
            'website_url' => $this->website_url ?: null,
            'config'      => $config,
            'brand_voice' => $enrichedVoice,
            'timezone'    => $this->timezone,
            'is_active'   => true,
        ], Auth::user());

        Auth::user()->switchBrand($brand);

        session()->flash('onboarding_done', $brand->name);
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.brand-onboarding', [
            'domainTypes' => config('brand.domain_types'),
            'timezones'   => \DateTimeZone::listIdentifiers(\DateTimeZone::AFRICA),
        ]);
    }
}
