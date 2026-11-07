<?php

namespace App\Http\Controllers;

use App\Jobs\DiscoverCompetitorsJob;
use App\Models\Competitor;
use App\Models\CompetitorSuggestion;
use App\Services\Competitors\Discovery\BrandProfile;
use App\Services\Competitors\Discovery\CompetitorDiscovery;
use App\Services\Competitors\CompetitorInsights;
use App\Services\Competitors\CompetitorParser;
use App\Services\Competitors\CompetitorScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CompetitorController extends Controller
{
    private const MAX_PER_BRAND = 15;

    public function index(CompetitorInsights $insights)
    {
        $brand = Auth::user()->activeBrand;
        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        return view('competitors.index', [
            'brand' => $brand, 'data' => $insights->summary($brand->id),
            'suggestions' => CompetitorSuggestion::where('brand_id', $brand->id)->where('status', 'suggested')
                ->orderByRaw("classification = 'big_platform'")->orderByDesc('score')->orderByDesc('query_hits')->get(),
            'discovery' => Cache::get(CompetitorDiscovery::statusKey($brand->id)),
            'searchConnected' => filled(config('services.tavily.key')),
        ]);
    }

    public function store(Request $request)
    {
        $brand = $this->brand();
        $request->validate(['domain' => 'required|string|max:255', 'name' => 'nullable|string|max:100']);

        $domain = CompetitorParser::normalizeDomain($request->input('domain'));
        if (!$domain) {
            return back()->withErrors(['domain' => 'Enter a valid website, like example.com.'])->withInput();
        }
        if ($brand->website_url && CompetitorParser::normalizeDomain($brand->website_url) === $domain) {
            return back()->withErrors(['domain' => 'That is your own site.'])->withInput();
        }
        if (Competitor::where('brand_id', $brand->id)->count() >= self::MAX_PER_BRAND) {
            return back()->withErrors(['domain' => 'Limit of ' . self::MAX_PER_BRAND . ' competitors per brand.'])->withInput();
        }
        if (Competitor::where('brand_id', $brand->id)->where('domain', $domain)->exists()) {
            return back()->withErrors(['domain' => 'Already tracking that competitor.'])->withInput();
        }

        Competitor::create([
            'brand_id' => $brand->id,
            'domain'   => $domain,
            'name'     => trim((string) $request->input('name')) ?: $domain,
        ]);

        return back()->with('message', "Added {$domain}. Use Scan now, or wait for Monday's automatic scan.");
    }

    /** Starts discovery in the background; it only creates suggestions. */
    public function discover()
    {
        $brand = $this->brand();
        if (BrandProfile::isThin(BrandProfile::build($brand))) {
            return back()->withErrors(['discover' => 'The system does not know enough about this brand yet. Add a business description or content pillars to the knowledge base first.']);
        }
        CompetitorDiscovery::remember($brand->id, ['state' => 'running', 'notes' => []]);
        DiscoverCompetitorsJob::dispatch($brand->id);

        return back()->with('message', 'Looking for competitors. This takes a minute or two; refresh the page to see the suggestions.');
    }

    public function accept(int $suggestion)
    {
        $brand = $this->brand();
        $s = CompetitorSuggestion::where('brand_id', $brand->id)->where('status', 'suggested')->findOrFail($suggestion);
        if ($s->classification === 'big_platform') {
            return back()->withErrors(['suggestion' => 'Large platforms are not tracked as competitors. Dismiss it, or add it by hand if you really want it.']);
        }
        if (Competitor::where('brand_id', $brand->id)->count() >= self::MAX_PER_BRAND) {
            return back()->withErrors(['suggestion' => 'Limit of ' . self::MAX_PER_BRAND . ' competitors per brand. Remove one first.']);
        }
        if (!Competitor::where('brand_id', $brand->id)->where('domain', $s->domain)->exists()) {
            Competitor::create(['brand_id' => $brand->id, 'domain' => $s->domain, 'name' => $s->name ?: ($s->homepage_title ? mb_substr($s->homepage_title, 0, 100) : $s->domain)]);
        }
        $s->update(['status' => 'accepted']);

        return back()->with('message', "Now tracking {$s->domain}. Use Scan now, or wait for Monday's automatic scan.");
    }

    public function dismiss(int $suggestion)
    {
        $s = CompetitorSuggestion::where('brand_id', $this->brand()->id)->where('status', 'suggested')->findOrFail($suggestion);
        $s->update(['status' => 'dismissed']);

        return back()->with('message', "Dismissed {$s->domain}. It will not be suggested again.");
    }

    public function destroy(int $competitor)
    {
        $c = $this->owned($competitor);
        $c->delete();

        return back()->with('message', "Stopped tracking {$c->domain}.");
    }

    public function scan(int $competitor, CompetitorScanner $scanner)
    {
        $c = $this->owned($competitor);
        $s = $scanner->scan($c);

        return $s->status === 'ok'
            ? back()->with('message', "Scanned {$c->domain}.")
            : back()->withErrors(['scan' => "{$c->domain}: {$s->error}"]);
    }

    private function brand()
    {
        $brand = Auth::user()->activeBrand;
        abort_unless($brand, 403);

        return $brand;
    }

    private function owned(int $id): Competitor
    {
        return Competitor::where('brand_id', $this->brand()->id)->findOrFail($id);
    }
}
