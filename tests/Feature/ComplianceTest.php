<?php

namespace Optima\DepotStock\Tests\Feature;

use Optima\DepotStock\Tests\TestCase;

class ComplianceTest extends TestCase
{
    public function test_compliance_clearances_route_loads(): void
    {
        $response = $this->get('/depot/compliance/clearances');
        $response->assertStatus(200);
    }
}
