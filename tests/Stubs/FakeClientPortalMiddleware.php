<?php
namespace Optima\DepotStock\Tests\Stubs;

use Closure;
use Illuminate\Http\Request;

class FakeClientPortalMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Always allow for tests
        return $next($request);
    }
}
