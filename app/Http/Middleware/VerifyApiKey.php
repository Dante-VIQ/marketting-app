<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyApiKey
{

public function handle(Request $request, Closure $next)
{
    $apiKey = $request->header('X-API-Key');
    $validKey = config('agent.api_key');

    // Never log the key itself. On mismatch, log only that a mismatch
    // occurred and where it came from — enough to debug, not enough to leak.
    if (!$apiKey || !hash_equals((string) $validKey, (string) $apiKey)) {
        Log::warning('API key rejected', [
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    return $next($request);
}
}
