<?php

namespace App\Http\Controllers;

use App\Services\Meta\ContentPublisher;
use App\Services\Meta\MetaConnection;
use App\Services\Meta\ResultsSync;
use Illuminate\Support\Facades\Auth;

class MetaController extends Controller
{
    public function index(MetaConnection $connection)
    {
        $brand = Auth::user()->activeBrand;
        abort_unless($brand, 403);

        return view('meta.index', ['brand' => $brand, 'status' => $connection->status(), 'publishing' => ContentPublisher::enabled(),
            'cap' => (int) config('meta.publishing.max_per_day')]);
    }

    public function sync(ResultsSync $sync)
    {
        $brand = Auth::user()->activeBrand;
        abort_unless($brand, 403);
        $r = $sync->run($brand->id);

        $msg = "Imported {$r['imported']} " . ($r['imported'] === 1 ? 'post' : 'posts') . ", refreshed results for {$r['updated']}.";

        return back()->with('message', $msg)->withErrors($r['errors']);
    }
}
