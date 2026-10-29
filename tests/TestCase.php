<?php

declare(strict_types=1);

namespace WeBRTeu\Sameday\Tests;

use Illuminate\Database\Migrations\Migration;
use Orchestra\Testbench\TestCase as Orchestra;
use WeBRTeu\Sameday\SamedayServiceProvider;
use WeBRTeu\Shipping\ShippingServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ShippingServiceProvider::class, SamedayServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('sameday.username', env('SAMEDAY_SANDBOX_USERNAME', 'test-user'));
        $app['config']->set('sameday.password', env('SAMEDAY_SANDBOX_PASSWORD', 'test-pass'));
        $app['config']->set('sameday.sandbox', true);
        $app['config']->set('sameday.pickup_point_id', env('SAMEDAY_SANDBOX_USERNAME') ? null : 12);
        $app['config']->set('sameday.contact_person_id', env('SAMEDAY_SANDBOX_USERNAME') ? null : 4);
        $app['config']->set('sameday.default_service', env('SAMEDAY_SANDBOX_DEFAULT_SERVICE', 7));
        $app['config']->set('sameday.locker_service', 15);
    }

    protected function defineDatabaseMigrations(): void
    {
        foreach ([
            __DIR__.'/../vendor/webrteu/shipping/database/migrations/create_shipments_tables.php.stub',
            __DIR__.'/../database/migrations/create_sameday_lockers_table.php.stub',
        ] as $stub) {
            /** @var Migration $migration */
            $migration = require $stub;
            $migration->up();
        }
    }
}
