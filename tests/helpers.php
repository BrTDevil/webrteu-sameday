<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Shipping\Data\AddressData;
use WeBRTeu\Shipping\Data\ParcelData;
use WeBRTeu\Shipping\Data\ShipmentData;

const SAMEDAY_HOST = 'https://sameday-api.demo.zitec.com';

function fixtureFile(string $name): string
{
    return (string) file_get_contents(__DIR__.'/Fixtures/'.$name);
}

/**
 * Răspunsuri false pe rute „METODĂ /cale” (fără query string). Autentificarea e inclusă implicit.
 *
 * @param  array<string, mixed>  $routes
 */
function fakeSameday(array $routes = []): void
{
    $routes += ['POST /api/authenticate' => Http::response(fixtureFile('auth.json'))];

    Http::fake(function (Request $request) use ($routes): mixed {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $key = $request->method().' '.$path;

        if (! array_key_exists($key, $routes)) {
            return Http::response(['message' => 'Rută nefalsificată: '.$key], 599);
        }

        $response = $routes[$key];

        return is_callable($response) ? $response($request) : $response;
    });
}

function address(): AddressData
{
    return new AddressData(
        name: 'Ion Popescu',
        phone: '+40 721 000 000',
        county: 'Brașov',
        city: 'Râșnov',
        street: 'Str. Republicii',
        number: '10',
        email: 'ion@example.com',
        block: 'A',
        apartment: '3',
        postalCode: '505400',
    );
}

/**
 * @param  array<string, mixed>  $overrides
 */
function shipmentData(array $overrides = []): ShipmentData
{
    return new ShipmentData(...$overrides + [
        'recipient' => address(),
        'parcels' => [new ParcelData(1.2, 20, 30, 10)],
        'reference' => 'CMD-1024',
        'codAmount' => 14970,
    ]);
}
