<?php

namespace Optima\DepotStock\Tests\Feature;

use Optima\DepotStock\Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_route_loads(): void
    {
        $response = $this->get('/depot/dashboard');
        $response->assertStatus(200);
    }
}
