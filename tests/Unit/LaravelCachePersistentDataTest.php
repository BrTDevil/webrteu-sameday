<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use WeBRTeu\Sameday\Persistence\LaravelCachePersistentData;

it('stores the token data under sameday:token:{username}', function (): void {
    $data = new LaravelCachePersistentData('magazin', 60);

    $data->set('token', 'abc');
    $data->set('expires_at', '2099-01-01 10:00:00');

    expect($data->cacheKey())->toBe('sameday:token:magazin')
        ->and($data->get('token'))->toBe('abc')
        ->and(Cache::get('sameday:token:magazin'))->toBe(['token' => 'abc', 'expires_at' => '2099-01-01 10:00:00']);
});

it('forgets keys set to null and the whole entry when empty', function (): void {
    $data = new LaravelCachePersistentData('magazin', 60);
    $data->set('token', 'abc');
    $data->set('expires_at', 'x');

    $data->set('token', null);
    expect($data->get('token'))->toBeNull()
        ->and($data->get('expires_at'))->toBe('x');

    $data->set('expires_at', null);
    expect(Cache::has('sameday:token:magazin'))->toBeFalse()
        ->and($data->get('missing'))->toBeNull();
});
