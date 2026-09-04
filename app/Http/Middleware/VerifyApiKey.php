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
        $validKey = env('LARAVEL_API_KEY');

        // Debug: log the keys for comparison
        Log::info('API Key Check', [
            'received' => $apiKey,
            'expected' => $validKey,
            'match' => $apiKey === $validKey,
        ]);

        if (!$apiKey || $apiKey !== $validKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
