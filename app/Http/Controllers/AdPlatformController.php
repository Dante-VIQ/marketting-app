<?php

namespace App\Http\Controllers;

use App\Models\AiAction;
use App\Models\Campaign;
use App\Services\AdPlatforms\AdPlatformException;
use App\Services\AdPlatforms\AdPlatformRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdPlatformController extends Controller
{
    public function __construct(private AdPlatformRegistry $registry)
    {
    }

    public function index()
    {
        $brand = Auth::user()->activeBrand;
        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $labels = config('ad_platforms.labels', []);
        $platforms = collect($this->registry->names())->map(fn ($name) => [
            'name'       => $name,
            'label'      => $labels[$name]['label'] ?? ucfirst($name),
            'env'        => $labels[$name]['env'] ?? [],
            'id_hint'    => $labels[$name]['id_hint'] ?? 'Campaign id on the platform',
            'configured' => $this->registry->get($name)->isConfigured(),
        ])->all();

        $campaigns = Campaign::where('brand_id', $brand->id)->orderBy('name')->get();

        $actions = AiAction::where('brand_id', $brand->id)
            ->whereNotNull('ad_operation')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('ad-platforms.index', [
            'brand'     => $brand,
            'platforms' => $platforms,
            'campaigns' => $campaigns,
            'actions'   => $actions,
            'maxChange' => (float) config('ad_platforms.safety.max_budget_change', 0.25),
        ]);
    }

    public function link(Request $request, int $campaign)
    {
        $c = $this->ownedCampaign($campaign);

        $data = $request->validate([
            'platform'    => ['required', 'string', 'in:' . implode(',', $this->registry->names())],
            'external_id' => ['required', 'string', 'max:64', 'regex:/^[0-9]+$/'],
        ]);

        $taken = Campaign::where('platform', $data['platform'])->where('external_id', $data['external_id'])
            ->where('id', '!=', $c->id)->exists();
        if ($taken) {
            return back()->withErrors(['external_id' => 'That platform campaign is already linked to another campaign.']);
        }

        $c->update($data);

        return back()->with('message', "Linked \"{$c->name}\" to {$data['platform']} campaign {$data['external_id']}.");
    }

    public function unlink(int $campaign)
    {
        $c = $this->ownedCampaign($campaign);
        $c->update(['platform' => null, 'external_id' => null]);

        return back()->with('message', "Unlinked \"{$c->name}\". The agent can no longer act on it automatically.");
    }

    /** Read-only call to the platform: proves the credentials and id work, changes nothing. */
    public function check(int $campaign)
    {
        $c = $this->ownedCampaign($campaign);
        if (!$c->platform || !$c->external_id) {
            return back()->withErrors(['check' => 'Link this campaign to a platform first.']);
        }

        try {
            $live = $this->registry->get($c->platform)->fetchCampaign($c->external_id);
        } catch (AdPlatformException $e) {
            return back()->withErrors(['check' => "{$c->name}: {$e->getMessage()}"]);
        }

        $budget = $live['daily_budget'] !== null ? number_format($live['daily_budget'], 2) : 'not set at campaign level';

        return back()->with('message', "{$c->name} is reachable. Live status: {$live['status']}; daily budget: {$budget}.");
    }

    private function ownedCampaign(int $id): Campaign
    {
        $brand = Auth::user()->activeBrand;
        abort_unless($brand, 403);

        return Campaign::where('brand_id', $brand->id)->findOrFail($id);
    }
}
