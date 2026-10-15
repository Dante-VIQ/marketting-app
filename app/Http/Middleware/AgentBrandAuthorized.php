<?php

namespace App\Http\Middleware;

use App\Models\AiAction;
use App\Models\Brand;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AgentBrandAuthorized
{
    public function handle(Request $request, Closure $next)
    {
        // Resolve the brand from the route, then from the request body
        // (camelCase or snake_case), then from the action the route targets.
        $brandId = $request->route('brandId')
            ?? $request->route('brand')
            ?? $request->input('brandId')
            ?? $request->input('brand_id');

        if (!$brandId && $request->route('actionId') !== null) {
            $action = AiAction::find($request->route('actionId'));

            if (!$action) {
                return response()->json(['error' => 'Action not found'], 404);
            }

            $brandId = $action->brand_id;
        }

        if (!$brandId) {
            // Some routes don't require brand context (e.g., /agent/ai/ping)
            return $next($request);
        }

        $brand = Brand::find($brandId);

        if (!$brand) {
            return response()->json([
                'error' => 'Brand not found',
                'brand_id' => $brandId,
            ], 404);
        }

        if (!$brand->is_active) {
            Log::warning('Agent attempted access to inactive brand', [
                'brand_id' => $brandId,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'error' => 'Brand is inactive',
            ], 403);
        }

        // Attach brand to request for downstream use
        $request->attributes->set('agent_brand', $brand);

        return $next($request);
    }
}