<?php

namespace Optima\DepotStock\Tests\Feature;

use Optima\DepotStock\Tests\TestCase;

class PortalTest extends TestCase
{
    public function test_portal_home_route_loads(): void
    {
        $response = $this->get('/portal');
        $response->assertStatus(200);
    }
}
