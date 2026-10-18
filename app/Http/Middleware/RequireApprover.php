<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Super-admins and the approvers they appoint. Everyone else sees a 404. */
class RequireApprover
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        abort_unless($user && $user->isApprover(), 404);

        return $next($request);
    }
}
