<?php

namespace Optima\DepotStock\Tests\Feature;

use Optima\DepotStock\Tests\TestCase;

class PaymentTest extends TestCase
{
    public function test_payments_index_route_loads(): void
    {
        $response = $this->get('/depot/payments');
        $response->assertStatus(200);
    }
}
