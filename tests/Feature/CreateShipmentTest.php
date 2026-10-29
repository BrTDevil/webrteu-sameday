<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Sameday\Exceptions\SamedayException;
use WeBRTeu\Shipping\Exceptions\ShipmentStateUnknownException;
use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;
use WeBRTeu\Shipping\Exceptions\UnsupportedOperationException;
use WeBRTeu\Shipping\Facades\Shipping;

it('creates an AWB with the cost in bani and the parcel numbers', function (): void {
    fakeSameday(['POST /api/awb' => Http::response(fixtureFile('awb-created.json'))]);

    $awb = Shipping::carrier('sameday')->createShipment(shipmentData());

    expect($awb->awb)->toBe('1ONB24000001')
        ->and($awb->carrier)->toBe('sameday')
        ->and($awb->cost)->toBe(2142)
        ->and($awb->parcelNumbers)->toBe(['1ONB24000001001', '1ONB24000001002']);

    Http::assertSent(fn (Request $request): bool => $request->url() === SAMEDAY_HOST.'/api/awb'
        && $request->method() === 'POST'
        && $request->hasHeader('X-AUTH-TOKEN', 'tok-123')
        && $request['cashOnDelivery'] === '149.7');
});

it('turns a 400 into a ShipmentValidationException with the field errors', function (): void {
    fakeSameday(['POST /api/awb' => Http::response(fixtureFile('awb-validation-error.json'), 400)]);

    try {
        Shipping::carrier('sameday')->createShipment(shipmentData());
        $this->fail('Trebuia să arunce ShipmentValidationException.');
    } catch (ShipmentValidationException $exception) {
        expect($exception->errors)->toBe([
            'awbRecipient.phoneNumber' => ['Numărul de telefon nu este valid.'],
            'parcels' => ['Greutatea este obligatorie.'],
        ]);
    }
});

it('falls back to the message when a 400 has no field errors', function (): void {
    fakeSameday(['POST /api/awb' => Http::response(['message' => 'Cont blocat'], 400)]);

    expect(fn () => Shipping::carrier('sameday')->createShipment(shipmentData()))
        ->toThrow(fn (ShipmentValidationException $e) => expect($e->errors)->toBe(['general' => ['Cont blocat']]));
});

it('throws ShipmentStateUnknownException on timeout without a second request', function (): void {
    $attempts = 0;
    fakeSameday(['POST /api/awb' => function () use (&$attempts): never {
        $attempts++;

        throw new ConnectionException('cURL error 28: Operation timed out');
    }]);

    expect(fn () => Shipping::carrier('sameday')->createShipment(shipmentData()))
        ->toThrow(fn (ShipmentStateUnknownException $e) => expect($e->reference)->toBe('CMD-1024'));

    expect($attempts)->toBe(1);
});

it('wraps other API errors in SamedayException', function (): void {
    fakeSameday(['POST /api/awb' => Http::response(['message' => 'Eroare internă'], 500)]);

    Shipping::carrier('sameday')->createShipment(shipmentData());
})->throws(SamedayException::class);

it('adds the open package tax from the services list', function (): void {
    fakeSameday([
        'GET /api/client/services' => Http::response(fixtureFile('services.json')),
        'POST /api/awb' => Http::response(fixtureFile('awb-created.json')),
    ]);

    Shipping::carrier('sameday')->createShipment(shipmentData(['openPackage' => true]));

    Http::assertSent(fn (Request $request): bool => $request->url() === SAMEDAY_HOST.'/api/awb' && $request['serviceTaxes'] === ['31']);
});

it('refuses open package when the account has no such tax', function (): void {
    fakeSameday(['GET /api/client/services' => Http::response(['data' => [], 'currentPage' => 1, 'perPage' => 500])]);

    Shipping::carrier('sameday')->createShipment(shipmentData(['openPackage' => true]));
})->throws(UnsupportedOperationException::class);

it('uses the default pickup point of the account when none is configured', function (): void {
    Config::set('sameday.pickup_point_id');
    fakeSameday([
        'GET /api/client/pickup-points' => Http::response(fixtureFile('pickup-points.json')),
        'POST /api/awb' => Http::response(fixtureFile('awb-created.json')),
    ]);

    Shipping::carrier('sameday')->createShipment(shipmentData());

    Http::assertSent(fn (Request $request): bool => $request->url() === SAMEDAY_HOST.'/api/awb' && $request['pickupPoint'] === '12');
});

it('falls back to the first pickup point and fails when there is none', function (): void {
    Config::set('sameday.pickup_point_id');
    $points = json_decode(fixtureFile('pickup-points.json'), true);
    $points['data'][1]['defaultPickupPoint'] = false;
    fakeSameday([
        'GET /api/client/pickup-points' => Http::sequence()
            ->push($points)
            ->push(['data' => [], 'currentPage' => 1, 'perPage' => 500]),
        'POST /api/awb' => Http::response(fixtureFile('awb-created.json')),
    ]);

    Shipping::carrier('sameday')->createShipment(shipmentData());
    Http::assertSent(fn (Request $request): bool => $request->url() === SAMEDAY_HOST.'/api/awb' && $request['pickupPoint'] === '11');

    expect(fn () => Shipping::carrier('sameday')->createShipment(shipmentData()))
        ->toThrow(SamedayException::class, 'niciun punct de ridicare');
});

it('quotes a shipment in bani', function (): void {
    fakeSameday(['POST /api/awb/estimate-cost' => Http::response(fixtureFile('estimate.json'))]);

    $quote = Shipping::carrier('sameday')->quote(shipmentData());

    expect($quote->amount)->toBe(1850)
        ->and($quote->currency)->toBe('RON')
        ->and($quote->serviceCode)->toBe('7');
});

it('handles an AWB response without a cost or parcels', function (): void {
    fakeSameday(['POST /api/awb' => Http::response(['awbNumber' => 'X1', 'awbCost' => null, 'parcels' => []])]);

    $awb = Shipping::carrier('sameday')->createShipment(shipmentData());

    expect($awb->cost)->toBeNull()
        ->and($awb->parcelNumbers)->toBe([]);
});
