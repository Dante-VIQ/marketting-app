<?php

namespace App\Http\Controllers;

use App\Models\RevenueEntry;
use App\Services\Impact\ImpactReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ImpactController extends Controller
{
    public function index(ImpactReport $report)
    {
        $brand = Auth::user()->activeBrand;
        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        return view('impact.index', ['brand' => $brand, 'r' => $report->build($brand->id)]);
    }

    /** A person records money they really received, from an affiliate or partner statement. */
    public function storeRevenue(Request $request)
    {
        $brand = $this->brand();
        $v = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'source' => 'required|string|max:60',
            'amount' => 'required|numeric|min:0|max:100000000',
            'currency' => ['required', 'regex:/^[A-Za-z]{3}$/'],
            'note' => 'nullable|string|max:200',
        ]);
        if (Carbon::createFromFormat('Y-m', $v['month'])->startOfMonth()->isFuture()) {
            return back()->withErrors(['month' => 'That month has not started yet.'])->withInput();
        }

        RevenueEntry::create(['brand_id' => $brand->id, 'month' => $v['month'] . '-01', 'source' => trim($v['source']), 'amount' => $v['amount'],
            'currency' => strtoupper($v['currency']), 'note' => $v['note'] ?? null, 'created_by' => Auth::id()]);

        return back()->with('message', 'Saved.');
    }

    public function destroyRevenue(int $entry)
    {
        RevenueEntry::where('brand_id', $this->brand()->id)->findOrFail($entry)->delete();

        return back()->with('message', 'Removed.');
    }

    private function brand()
    {
        $brand = Auth::user()->activeBrand;
        abort_unless($brand, 403);

        return $brand;
    }
}
