<?php

namespace App\Http\Middleware;

use App\Models\AgentAuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MonitorAgentIP
{
    /**
     * Allowed IPs for the agent (leave empty to allow any).
     * Set AGENT_ALLOWED_IPS in .env as comma-separated list.
     */
    protected function allowedIps(): array
    {
        $env = env('AGENT_ALLOWED_IPS', '');
        return $env ? array_map('trim', explode(',', $env)) : [];
    }

    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        $allowed = $this->allowedIps();

        // If allowlist is set and IP is not in it, reject
        if (!empty($allowed) && !in_array($ip, $allowed)) {
            Log::warning('🚫 Agent request from unauthorized IP', [
                'ip' => $ip,
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'error' => 'Unauthorized network.',
            ], 403);
        }

        // Log every request
        try {
            AgentAuditLog::create([
                'ip_address' => $ip,
                'method'     => $request->method(),
                'path'       => $request->path(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 255),
                'brand_id'   => $request->route('brandId') ?? null,
                'metadata'   => [
                    'query' => $request->query(),
                ],
            ]);
        } catch (\Exception $e) {
            // Never block the request if logging fails
            Log::warning('Failed to write agent audit log: ' . $e->getMessage());
        }

        return $next($request);
    }
}