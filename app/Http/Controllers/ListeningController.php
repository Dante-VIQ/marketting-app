<?php

namespace App\Http\Controllers;

use App\Models\ListeningQuery;
use App\Models\Mention;
use App\Services\Listening\ListeningCollector;
use App\Services\Listening\ListeningInsights;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListeningController extends Controller
{
    private const MAX_QUERIES = 20;

    public function index(ListeningInsights $insights, ListeningCollector $collector)
    {
        $brand = Auth::user()->activeBrand;
        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $collector->ensureDefaults($brand);   // no network: just makes sure there is something to listen for

        return view('listening.index', ['brand' => $brand, 'data' => $insights->summary($brand->id)]);
    }

    public function check(ListeningCollector $collector)
    {
        $brand = $this->brand();
        $r = $collector->collectForBrand($brand);

        return back()->with('message', "Checked {$r['queries']} " . ($r['queries'] === 1 ? 'search' : 'searches')
            . ": {$r['new_mentions']} new " . ($r['new_mentions'] === 1 ? 'mention' : 'mentions') . '.');
    }

    public function store(Request $request)
    {
        $brand = $this->brand();
        $data = $request->validate([
            'term' => 'required|string|min:2|max:120',
            'kind' => 'required|in:brand,topic',
        ]);
        $term = trim(preg_replace('/\s+/', ' ', str_replace('"', '', $data['term'])));
        if (mb_strlen($term) < 2) {
            return back()->withErrors(['term' => 'Enter at least 2 characters.']);
        }
        if (ListeningQuery::where('brand_id', $brand->id)->where('is_active', true)->count() >= self::MAX_QUERIES) {
            return back()->withErrors(['term' => 'Limit of ' . self::MAX_QUERIES . ' searches per brand. Remove one first.']);
        }

        $q = ListeningQuery::updateOrCreate(['brand_id' => $brand->id, 'kind' => $data['kind'], 'term' => $term], ['is_active' => true]);

        return back()->with('message', ($q->wasRecentlyCreated ? "Now listening for \"{$term}\"." : "Already listening for \"{$term}\".")
            . ' Use "Check now" to look right away.');
    }

    public function destroy(int $query)
    {
        $q = ListeningQuery::where('brand_id', $this->brand()->id)->findOrFail($query);
        if ($q->kind === 'competitor') {
            return back()->withErrors(['term' => 'Competitor searches follow the Competitors page. Remove the competitor there.']);
        }
        $q->delete();

        return back()->with('message', "Stopped listening for \"{$q->term}\".");
    }

    public function dismiss(int $mention)
    {
        Mention::where('brand_id', $this->brand()->id)->findOrFail($mention)->update(['is_dismissed' => true]);

        return back()->with('message', 'Mention hidden.');
    }

    private function brand()
    {
        $brand = Auth::user()->activeBrand;
        abort_unless($brand, 403);

        return $brand;
    }
}
