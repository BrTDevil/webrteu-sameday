<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use WeBRTeu\Sameday\SamedayServiceProvider;

it('publishes config, migration and views', function (string $tag): void {
    expect(ServiceProvider::pathsToPublish(SamedayServiceProvider::class, $tag))->toHaveCount(1);
})->with(['sameday-config', 'sameday-migrations', 'sameday-views']);
