<?php

namespace Optima\DepotStock\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * Usage: ->middleware('role:admin')
     *        ->middleware('role:admin,billing')
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'Unauthenticated.');
        }

        // If no roles specified, just continue
        if (empty($roles)) {
            return $next($request);
        }

        // If user has no roles relation, block
        if (!method_exists($user, 'roles')) {
            abort(403, 'No roles relation defined on User model.');
        }

        $hasRole = $user->roles()
            ->whereIn('name', $roles)
            ->exists();

        if (!$hasRole) {
            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}