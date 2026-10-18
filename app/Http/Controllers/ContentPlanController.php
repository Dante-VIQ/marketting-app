<?php

namespace App\Http\Controllers;

use App\Models\ContentIdea;
use App\Models\Persona;
use App\Services\ContentPlan\ContentPlanInsights;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContentPlanController extends Controller
{
    public function index(ContentPlanInsights $insights)
    {
        $brand = Auth::user()->activeBrand;
        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }
        $ideas = ContentIdea::where('brand_id', $brand->id)->latest()->get()->groupBy('status');

        return view('content-plan.index', [
            'brand' => $brand,
            'ideas' => $ideas,
            'suggestions' => $insights->suggestions($brand->id),
            'performance' => $insights->performance($brand->id),
            'personas' => Persona::where('brand_id', $brand->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $brand = $this->brand();
        $data = $this->validatedIdea($request, $brand->id) + ['brand_id' => $brand->id];
        $data['source'] = in_array($request->input('source'), ['manual', 'competitor_gap', 'listening', 'keyword', 'persona'], true) ? $request->input('source') : 'manual';
        $data['source_detail'] = mb_substr((string) $request->input('source_detail'), 0, 300) ?: null;
        ContentIdea::create($data);

        return back()->with('message', 'Added to your plan.');
    }

    public function update(Request $request, int $idea)
    {
        $i = $this->find($idea);
        $i->update($this->validatedIdea($request, $i->brand_id) + $request->validate(['status' => 'nullable|in:idea,ready,skipped']));

        return back()->with('message', 'Saved.');
    }

    /** You posted it yourself: record where and when. */
    public function posted(Request $request, int $idea)
    {
        $i = $this->find($idea);
        $data = $request->validate(['post_url' => 'nullable|url|max:1000', 'posted_on' => 'nullable|date|before_or_equal:today']);
        $i->update(['status' => 'posted', 'post_url' => $data['post_url'] ?? null,
            'posted_at' => !empty($data['posted_on']) ? $data['posted_on'] : now()]);

        return back()->with('message', 'Marked as posted. Add its results in a day or two.');
    }

    public function results(Request $request, int $idea)
    {
        $i = $this->find($idea);
        abort_unless($i->status === 'posted', 422);
        $data = $request->validate(['impressions' => 'nullable|integer|min:0|max:2000000000', 'engagements' => 'nullable|integer|min:0|max:2000000000',
            'clicks' => 'nullable|integer|min:0|max:2000000000', 'shares' => 'nullable|integer|min:0|max:2000000000']);
        if (isset($data['impressions'], $data['engagements']) && $data['engagements'] > $data['impressions']) {
            return back()->withErrors(['engagements' => 'Engagements cannot be more than impressions (reach).']);
        }
        // Blank stays null: "not measured" is not the same as 0.
        $i->update($data + ['measured_at' => now()]);

        return back()->with('message', 'Results saved.');
    }

    public function destroy(int $idea)
    {
        $this->find($idea)->delete();

        return back()->with('message', 'Removed.');
    }

    private function validatedIdea(Request $request, int $brandId): array
    {
        $data = $request->validate([
            'title' => 'required|string|min:3|max:200',
            'topic' => 'nullable|string|max:80',
            'platform' => 'nullable|in:' . implode(',', array_keys(ContentIdea::PLATFORMS)),
            'format' => 'nullable|in:' . implode(',', array_keys(ContentIdea::FORMATS)),
            'copy' => 'nullable|string|max:5000',
            'planned_for' => 'nullable|date',
            'persona_id' => 'nullable|integer',
        ]);
        if (!empty($data['persona_id']) && !Persona::where('brand_id', $brandId)->whereKey($data['persona_id'])->exists()) {
            $data['persona_id'] = null;
        }

        return $data;
    }

    private function find(int $id): ContentIdea
    {
        return ContentIdea::where('brand_id', $this->brand()->id)->findOrFail($id);
    }

    private function brand()
    {
        $brand = Auth::user()->activeBrand;
        abort_unless($brand, 403);

        return $brand;
    }
}
