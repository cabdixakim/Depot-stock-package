<?php

namespace Optima\DepotStock\Tests\Feature;

use Optima\DepotStock\Tests\TestCase;

class DipTest extends TestCase
{
    public function test_dips_index_route_loads(): void
    {
        $response = $this->get('/depot/dips');
        $response->assertStatus(200);
    }
}
