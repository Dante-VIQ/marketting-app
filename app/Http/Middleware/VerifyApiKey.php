<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key');
        $validKey = env('AGENT_API_KEY');

        if (!$apiKey || $apiKey !== $validKey) {
            return response()->json([
                'error' => 'Unauthorized. Invalid API key.',
            ], 401);
        }

        return $next($request);
    }
}
