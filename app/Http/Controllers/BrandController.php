<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Services\BrandManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BrandController extends Controller
{
    protected BrandManagementService $brandService;

    public function __construct(BrandManagementService $brandService)
    {
        $this->brandService = $brandService;
    }

    public function index()
    {
        $brands = Auth::user()->brands()->withCount('users')->with('users')->get();

        // Load pending invitations per brand (for the invite management UI)
        $pendingInvitations = \App\Models\BrandInvitation::whereIn('brand_id', $brands->pluck('id'))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->with('inviter')
            ->get()
            ->groupBy('brand_id');

        return view('brands.index', compact('brands', 'pendingInvitations'));
    }

    public function integrations(Brand $brand)
    {
        $this->authorize('view', $brand);
        return view('brands.integrations', compact('brand'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:brands',
            'website_url' => 'nullable|url|max:255',
            'domain_type' => 'required|in:' . implode(',', array_keys(config('brand.domain_types'))),
            'config' => 'nullable|json',
            'brand_voice' => 'required|string',
            'timezone' => 'required|string',
        ]);

        $config = $request->config ? json_decode($request->config, true) : [];

        $brand = $this->brandService->createBrand([
            'name' => $request->name,
            'domain_type' => $request->domain_type,
            'website_url' => $request->website_url,
            'config' => $config,
            'brand_voice' => $request->brand_voice,
            'timezone' => $request->timezone,
            'is_active' => $request->boolean('is_active', true),
        ], Auth::user());

        return response()->json([
            'success' => true,
            'brand' => $brand,
        ]);
    }

    public function update(Request $request, Brand $brand)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name,' . $brand->id,
            'website_url' => 'nullable|url|max:255',
            'domain_type' => 'required|in:' . implode(',', array_keys(config('brand.domain_types'))),
            'config' => 'nullable|json',
            'brand_voice' => 'required|string',
            'timezone' => 'required|string',
        ]);

        $config = $request->config ? json_decode($request->config, true) : [];

        $updated = $this->brandService->updateBrand($brand, [
            'name' => $request->name,
            'website_url' => $request->website_url,
            'domain_type' => $request->domain_type,
            'config' => $config,
            'brand_voice' => $request->brand_voice,
            'timezone' => $request->timezone,
            'is_active' => $request->boolean('is_active', true),
        ], Auth::user());

        return response()->json([
            'success' => true,
            'brand' => $updated,
        ]);
    }

    public function toggleActive(Brand $brand)
    {
        $status = $this->brandService->toggleActive($brand);

        return response()->json([
            'success' => true,
            'is_active' => $status,
        ]);
    }
}
