<?php

namespace Optima\DepotStock\Tests\Feature;

use Optima\DepotStock\Tests\TestCase;

class ClientTest extends TestCase
{
    public function test_clients_index_route_loads(): void
    {
        $response = $this->get('/depot/clients');
        $response->assertStatus(200);
    }
}
