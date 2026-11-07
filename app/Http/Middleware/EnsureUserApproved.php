<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Signed-in users wait on a holding page until the platform administrator approves them. */
class EnsureUserApproved
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && !$user->isApproved()) {
            return redirect()->route('approval.pending');
        }

        return $next($request);
    }
}
