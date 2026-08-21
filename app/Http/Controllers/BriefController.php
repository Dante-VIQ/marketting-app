<?php

namespace App\Http\Controllers;

use App\Models\AiBrief;
use App\Models\Brand;
use App\Services\AI\BriefGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BriefController extends Controller
{
    protected BriefGeneratorService $briefGenerator;

    public function __construct(BriefGeneratorService $briefGenerator)
    {
        $this->briefGenerator = $briefGenerator;
    }

    /**
     * Display all AI briefs.
     */
    public function index()
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $briefs = AiBrief::where('brand_id', $brand->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get brief statistics
        $totalBriefs = $briefs->count();
        $lastBrief = $briefs->first();
        $briefsThisWeek = AiBrief::where('brand_id', $brand->id)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        return view('briefs.index', compact(
            'brand',
            'briefs',
            'totalBriefs',
            'lastBrief',
            'briefsThisWeek'
        ));
    }

    /**
     * Show a specific brief.
     */
    public function show($id)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        $brief = AiBrief::where('brand_id', $brand->id)
            ->with('actions')
            ->findOrFail($id);

        return view('briefs.show', compact('brief'));
    }

    /**
     * Generate a new AI brief manually.
     */
    public function generate(Request $request)
    {
        $user = Auth::user();
        $brand = $user->activeBrand;

        if (!$brand) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Brand not found'], 404);
            }
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        try {
            // Check if AI is available
            $aiGateway = app(\App\Services\AI\AiGatewayService::class);
            if (!$aiGateway->isAvailable()) {
                throw new \Exception('AI service is not available. Please check your Ollama connection.');
            }

            // Generate the brief
            $brief = $this->briefGenerator->generateForBrand($brand);

            if (!$brief) {
                throw new \Exception('Brief generation returned null. Check logs for details.');
            }

            $message = 'AI brief generated successfully!';
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'brief_id' => $brief->id,
                    'actions_count' => $brief->actions()->count(),
                    'revenue_impact' => $brief->estimated_revenue_impact,
                    'confidence_score' => $brief->confidence_score,
                ]);
            }

            return redirect()->route('briefs.index')->with('message', $message);
        } catch (\Exception $e) {
            $error = 'Failed to generate brief: ' . $e->getMessage();
            Log::error('Brief generation failed', [
                'brand_id' => $brand->id,
                'error' => $e->getMessage(),
            ]);
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $error,
                ], 500);
            }

            return redirect()->route('briefs.index')->with('error', $error);
        }
    }
}