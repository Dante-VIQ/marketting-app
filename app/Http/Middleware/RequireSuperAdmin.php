<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Allows only users holding the global "super-admin" role. Everyone else sees a 404. */
class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        abort_unless($user && $user->isSuperAdmin(), 404);

        return $next($request);
    }
}
