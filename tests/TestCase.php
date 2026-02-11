<?php

namespace Optima\DepotStock\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // You can add common setup here

    protected function setUp(): void
    {
        parent::setUp();
        // Register fake middleware for 'role' and 'client.portal'
        $this->app['router']->aliasMiddleware('role', \Optima\\DepotStock\\Tests\\Stubs\\FakeRoleMiddleware::class);
        $this->app['router']->aliasMiddleware('client.portal', \Optima\\DepotStock\\Tests\\Stubs\\FakeClientPortalMiddleware::class);
        // Register a dummy login route
        $this->app['router']->get('login', function () {
            return 'Fake login page';
        })->name('login');
    }

    protected function getPackageProviders($app)
    {
        return [
            \Optima\DepotStock\DepotStockServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
