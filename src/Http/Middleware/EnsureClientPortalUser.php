<?php

namespace Optima\DepotStock\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientPortalUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // not logged in → let the normal auth middleware handle it
        if (!$user) {
            abort(403, 'Unauthenticated.');
        }

        // must have client role + client_id linked
        if (!method_exists($user, 'hasRole') || !$user->hasRole('client') || !$user->client_id) {
            abort(403, 'You are not allowed to access the client portal.');
        }

        return $next($request);
    }
}