<?php

namespace Optima\DepotStock\Tests\Feature;

use Optima\DepotStock\Tests\TestCase;

class InvoiceTest extends TestCase
{
    public function test_invoices_index_route_loads(): void
    {
        $response = $this->get('/depot/invoices');
        $response->assertStatus(200);
    }
}
