<?php
namespace Optima\DepotStock\Tests\Stubs;

use Closure;
use Illuminate\Http\Request;

class FakeRoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // Always allow for tests
        return $next($request);
    }
}
