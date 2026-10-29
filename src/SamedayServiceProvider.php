<?php

declare(strict_types=1);

namespace WeBRTeu\Sameday;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use WeBRTeu\Sameday\Commands\PickupPointsCommand;
use WeBRTeu\Sameday\Commands\ServicesCommand;
use WeBRTeu\Sameday\Commands\SyncLockersCommand;
use WeBRTeu\Shipping\ShippingManager;

class SamedayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sameday.php', 'sameday');

        $this->app->singleton(SamedayClientFactory::class);
        $this->app->singleton(SamedayCarrier::class);

        $this->callAfterResolving(ShippingManager::class, function (ShippingManager $shipping): void {
            $shipping->extend('sameday', fn (Container $app): SamedayCarrier => $app->make(SamedayCarrier::class));
        });
    }

    public function boot(): void
    {
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'sameday');

        $this->publishes([
            __DIR__.'/../config/sameday.php' => $this->app->configPath('sameday.php'),
        ], 'sameday-config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_sameday_lockers_table.php.stub' => $this->app->databasePath('migrations/'.date('Y_m_d_His').'_create_sameday_lockers_table.php'),
        ], 'sameday-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/sameday'),
        ], 'sameday-views');

        $this->commands([SyncLockersCommand::class, ServicesCommand::class, PickupPointsCommand::class]);
    }
}
