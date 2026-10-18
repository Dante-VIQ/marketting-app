<?php

namespace App\Http\Controllers;

use App\Models\Competitor;
use App\Services\Competitors\CompetitorInsights;
use App\Services\Competitors\CompetitorParser;
use App\Services\Competitors\CompetitorScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompetitorController extends Controller
{
    private const MAX_PER_BRAND = 15;

    public function index(CompetitorInsights $insights)
    {
        $brand = Auth::user()->activeBrand;
        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        return view('competitors.index', ['brand' => $brand, 'data' => $insights->summary($brand->id)]);
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
