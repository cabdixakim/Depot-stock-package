<?php

namespace Optima\DepotStock;

use Illuminate\Support\ServiceProvider;
use Optima\DepotStock\Models\Offload;
use Optima\DepotStock\Observers\OffloadObserver;

class DepotStockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/depot-stock.php', 'depot-stock');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'depot-stock');

        // 🧭 Hook Offload lifecycle to pool ledger
        Offload::observe(OffloadObserver::class);

    //     $this->publishes([
    //         __DIR__.'/../config/depot-stock.php' => config_path('depot-stock.php'),
    //     ], 'depot-stock-config');

    //     $this->publishes([
    //         __DIR__.'/Resources/views' => resource_path('views/vendor/depot-stock'),
    //     ], 'depot-stock-views');

    //     $this->publishes([
    //         __DIR__.'/../database/migrations' => database_path('migrations'),
    //     ], 'depot-stock-migrations');
     }
}
