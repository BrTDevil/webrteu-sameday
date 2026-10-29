<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Sameday\Facades\Sameday;
use WeBRTeu\Sameday\SamedayCarrier;
use WeBRTeu\Sameday\SamedayClientFactory;
use WeBRTeu\Shipping\Enums\PickupPointType;
use WeBRTeu\Shipping\Facades\Shipping;

it('lists the services and marks the easybox ones', function (): void {
    fakeSameday(['GET /api/client/services' => Http::response(fixtureFile('services.json'))]);

    $services = Shipping::carrier('sameday')->services();

    expect($services->pluck('code')->all())->toBe(['7', '15'])
        ->and($services[0]->pickupPoint)->toBeFalse()
        ->and($services[1]->pickupPoint)->toBeTrue();
});

it('lists the lockers across pages, filtered by county and city', function (): void {
    fakeSameday(['GET /api/client/lockers' => Http::sequence()
        ->push(fixtureFile('lockers-page-1.json'))->push(fixtureFile('lockers-page-2.json'))
        ->push(fixtureFile('lockers-page-1.json'))->push(fixtureFile('lockers-page-2.json'))]);

    $all = Sameday::pickupPoints();
    $rasnov = Sameday::pickupPoints('Brasov', 'RASNOV');

    expect($all)->toHaveCount(3)
        ->and($rasnov)->toHaveCount(1)
        ->and($rasnov[0]->id)->toBe('102')
        ->and($rasnov[0]->type)->toBe(PickupPointType::Locker)
        ->and($rasnov[0]->supportsCod)->toBeFalse()
        ->and($all[0]->supportsCod)->toBeTrue()
        ->and($all[0]->lat)->toBe(45.6427)
        ->and($all[0]->postalCode)->toBe('500001');
});

it('reads the card payment flag in its different shapes', function (mixed $payment, bool $expected): void {
    expect(SamedayCarrier::pickupPoint(['lockerId' => 1, 'supportedPayment' => $payment])->supportsCod)->toBe($expected);
})->with([[true, true], ['Card', true], ['', false], [null, false], [2, true]]);

it('chooses the API host from sandbox, country and base url', function (): void {
    $factory = new SamedayClientFactory;

    expect($factory->host())->toBe('https://sameday-api.demo.zitec.com');

    Config::set('sameday.sandbox', false);
    expect($factory->host())->toBe('https://api.sameday.ro');

    Config::set('sameday.country', 'HU');
    expect($factory->host())->toBe('https://api.sameday.hu');

    Config::set('sameday.country', 'XX');
    expect($factory->host())->toBe('https://api.sameday.ro');

    Config::set('sameday.base_url', 'https://proxy.example/');
    expect($factory->host())->toBe('https://proxy.example');
});

it('registers sameday in the shipping manager', function (): void {
    expect(Shipping::available())->toContain('sameday')
        ->and(Shipping::carrier('sameday')->name())->toBe('sameday')
        ->and(Sameday::sdk())->toBeInstanceOf(\Sameday\Sameday::class);
});

it('prints the services and pickup points', function (): void {
    fakeSameday([
        'GET /api/client/services' => Http::response(fixtureFile('services.json')),
        'GET /api/client/pickup-points' => Http::response(fixtureFile('pickup-points.json')),
    ]);

    $this->artisan('sameday:services')
        ->expectsTable(['Id', 'Nume', 'Easybox'], [['7', 'Nextday 24H', 'nu'], ['15', 'Locker NextDay', 'da']])
        ->assertSuccessful();

    $this->artisan('sameday:pickup-points')
        ->expectsTable(['Id', 'Alias', 'Adresă', 'Implicit', 'Persoane de contact (id: nume)'], [
            ['11', 'Secundar', 'Str. Depozitului 1', 'nu', '3: Depozit Secundar'],
            ['12', 'Sediu', 'Str. Fabricii 5', 'da', '4: Ana Ionescu'],
        ])
        ->assertSuccessful();
});
