<?php

namespace App\Http\Middleware;

use App\Models\AiAction;
use App\Models\Brand;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = (string) $request->header('X-API-Key', '');

        if ($apiKey === '') {
            return $this->reject($request, 401, 'Unauthorized');
        }

        // 1. Shared key (all brands) — optional once per-brand keys are rolled out.
        $sharedKey = (string) config('agent.api_key');
        if (config('agent.allow_shared_key', true) && $sharedKey !== '' && hash_equals($sharedKey, $apiKey)) {
            return $next($request);
        }

        // 2. Per-brand key: only valid for requests that target that brand.
        $brand = Brand::where('agent_api_key_hash', hash('sha256', $apiKey))->first();
        if (!$brand) {
            return $this->reject($request, 401, 'Unauthorized');
        }

        if (!$this->targetsOnlyBrand($request, $brand)) {
            Log::warning('Brand API key used outside its brand', [
                'brand_id' => $brand->id,
                'ip'       => $request->ip(),
                'path'     => $request->path(),
            ]);
            return response()->json(['error' => 'Forbidden for this brand'], 403);
        }

        return $next($request);
    }

    /**
     * A brand key may only touch its own brand: the brand named in the route/body
     * must match, and any action referenced must belong to it. Routes naming no
     * brand (e.g. /brands, which lists every brand) are refused except the ping.
     */
    private function targetsOnlyBrand(Request $request, Brand $brand): bool
    {
        $named = $request->route('brandId')
            ?? $request->route('brand')
            ?? $request->input('brandId')
            ?? $request->input('brand_id');

        $actionId = $request->route('actionId') ?? $request->input('action_id');

        if ($named === null && $actionId === null) {
            return $request->routeIs('agent.ai.ping');
        }

        if ($named !== null && (int) $named !== (int) $brand->id) {
            return false;
        }

        if ($actionId !== null) {
            $actionBrand = AiAction::whereKey($actionId)->value('brand_id');
            // Unknown action: let the brand-check middleware / controller 404 it.
            if ($actionBrand !== null && (int) $actionBrand !== (int) $brand->id) {
                return false;
            }
        }

        return true;
    }

    private function reject(Request $request, int $status, string $message)
    {
        // Never log the key itself — only that a mismatch occurred and from where.
        Log::warning('API key rejected', ['ip' => $request->ip(), 'path' => $request->path()]);
        return response()->json(['error' => $message], $status);
    }
}
