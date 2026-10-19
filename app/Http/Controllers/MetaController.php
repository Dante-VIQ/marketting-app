<?php

namespace App\Http\Controllers;

use App\Models\AdInsightRow;
use App\Models\AudienceComment;
use App\Services\Meta\AdAudienceInsights;
use App\Services\Meta\AdInsightsSync;
use App\Services\Meta\AudienceResearch;
use App\Services\Meta\CommentSync;
use App\Services\Meta\ContentPublisher;
use App\Services\Meta\LeadSync;
use App\Services\Meta\MetaApiException;
use App\Services\Meta\MetaCapabilities;
use App\Services\Meta\MetaConnection;
use App\Services\Meta\ResultsSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MetaController extends Controller
{
    public function index(Request $request, MetaConnection $connection, AudienceResearch $research)
    {
        $brand = Auth::user()->activeBrand;
        abort_unless($brand, 403);

        $q = trim((string) $request->query('q', ''));
        $interests = [];
        $researchError = null;
        if ($q !== '') {
            try {
                $interests = $research->interests($q);
            } catch (MetaApiException $e) {
                $researchError = trim($e->getMessage() . ' ' . $e->hint());
            }
        }

        $comments = AudienceComment::where('brand_id', $brand->id);

        return view('meta.index', [
            'brand' => $brand, 'status' => $connection->status(), 'publishing' => ContentPublisher::enabled(),
            'cap' => (int) config('meta.publishing.max_per_day'),
            'ads' => AdAudienceInsights::build(AdInsightRow::where('brand_id', $brand->id)->get()),
            'commentCount' => (clone $comments)->count(),
            'questions' => (clone $comments)->where('is_question', true)->orderByDesc('commented_at')->limit(8)->get(),
            'sentiment' => (clone $comments)->whereNotNull('sentiment')->selectRaw('sentiment, count(*) as n')->groupBy('sentiment')->pluck('n', 'sentiment')->all(),
            'q' => $q, 'interests' => $interests, 'researchError' => $researchError,
            'recommended' => MetaCapabilities::recommendedScopes(),
        ]);
    }

    public function sync(ResultsSync $sync)
    {
        $brand = Auth::user()->activeBrand;
        abort_unless($brand, 403);
        $r = $sync->run($brand->id);

        $msg = "Imported {$r['imported']} " . ($r['imported'] === 1 ? 'post' : 'posts') . ", refreshed results for {$r['updated']}.";

        return back()->with('message', $msg)->withErrors($r['errors']);
    }

    public function syncData(Request $request, AdInsightsSync $ads, CommentSync $comments, LeadSync $leads)
    {
        $brand = Auth::user()->activeBrand;
        abort_unless($brand, 403);
        $what = $request->validate(['what' => 'required|in:ads,comments,leads'])['what'];

        if ($what === 'ads') {
            $r = $ads->run($brand->id);
            $msg = "Saved {$r['rows']} ad rows.";
        } elseif ($what === 'comments') {
            $r = $comments->run($brand->id);
            $msg = "Saved {$r['saved']} new comments.";
        } else {
            $r = $leads->run($brand->id);
            $msg = "Added {$r['created']} new leads.";
        }

        return back()->with('message', $msg)->withErrors($r['errors']);
    }
}
