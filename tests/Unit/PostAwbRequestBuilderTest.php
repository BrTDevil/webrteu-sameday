<?php

declare(strict_types=1);

use WeBRTeu\Sameday\Support\PostAwbRequestBuilder;
use WeBRTeu\Shipping\Data\AddressData;
use WeBRTeu\Shipping\Data\ParcelData;
use WeBRTeu\Shipping\Data\ShipmentData;
use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;
use WeBRTeu\Shipping\Exceptions\UnsupportedOperationException;

function builder(?int $default = 7, ?int $locker = 15): PostAwbRequestBuilder
{
    return new PostAwbRequestBuilder(12, 4, $default, $locker);
}

/**
 * @return array<string, mixed>
 */
function body(ShipmentData $shipment, array $taxes = []): array
{
    parse_str(builder()->awb($shipment, $taxes)->buildRequest()->getBody()->getBody(), $body);

    return $body;
}

it('converts cash on delivery from bani to lei and pays as client', function (): void {
    $body = body(shipmentData(['declaredValue' => 20000]));

    expect($body['cashOnDelivery'])->toBe('149.7')
        ->and($body['insuredValue'])->toBe('200')
        ->and($body['awbPayment'])->toBe('1')
        ->and($body['pickupPoint'])->toBe('12')
        ->and($body['contactPerson'])->toBe('4')
        ->and($body['service'])->toBe('7')
        ->and($body['packageType'])->toBe('0')
        ->and($body['clientInternalReference'])->toBe('CMD-1024')
        ->and($body)->not->toHaveKey('lockerLastMile');
});

it('sends the recipient with a normalized phone and the street line only', function (): void {
    $recipient = body(shipmentData())['awbRecipient'];

    expect($recipient)->toMatchArray([
        'name' => 'Ion Popescu',
        'phoneNumber' => '0721000000',
        'address' => 'Str. Republicii nr. 10, bl. A, ap. 3',
        'cityString' => 'Râșnov',
        'countyString' => 'Brașov',
        'email' => 'ion@example.com',
        'postalCode' => '505400',
    ]);
});

it('delivers to an easybox with the locker service and lockerLastMile', function (): void {
    $body = body(shipmentData(['pickupPointId' => '101']));

    expect($body['lockerLastMile'])->toBe('101')
        ->and($body['service'])->toBe('15')
        ->and($body['awbRecipient']['address'])->toBe('Str. Republicii nr. 10, bl. A, ap. 3');
});

it('sends every parcel and the service from the shipment', function (): void {
    $body = body(shipmentData(['parcels' => [new ParcelData(1.0), new ParcelData(2.5, 10, 20, 30)], 'serviceCode' => '99']), [31]);

    expect($body['parcels'])->toHaveCount(2)
        ->and($body['parcels'][1])->toBe(['weight' => '2.5', 'width' => '20', 'length' => '10', 'height' => '30'])
        ->and($body['service'])->toBe('99')
        ->and($body['serviceTaxes'])->toBe(['31']);
});

it('sends envelopes as package type ENVELOPE', function (): void {
    $body = body(shipmentData(['parcels' => [], 'envelopes' => 2]));

    expect($body['packageType'])->toBe('1')
        ->and($body['parcels'])->toHaveCount(2);
});

it('puts the company and the observations on the AWB', function (): void {
    $address = new AddressData('Ion Popescu', '0721000000', 'Brașov', 'Brașov', 'Str. X', '1', contactPerson: 'Ana', company: 'Exemplu SRL');
    $body = body(shipmentData(['recipient' => $address, 'contents' => 'Haine', 'observations' => 'Sunați înainte']));

    expect($body['awbRecipient']['name'])->toBe('Ana')
        ->and($body['awbRecipient']['companyName'])->toBe('Exemplu SRL')
        ->and($body['observation'])->toBe('Sunați înainte')
        ->and($body['clientObservation'])->toBe('Haine')
        ->and(body(shipmentData(['contents' => 'Haine']))['observation'])->toBe('Haine')
        ->and(body(shipmentData()))->not->toHaveKey('observation');
});

it('builds the estimation request', function (): void {
    parse_str(builder()->estimation(shipmentData())->buildRequest()->getBody()->getBody(), $body);

    expect($body['cashOnDelivery'])->toBe('149.7')
        ->and($body['service'])->toBe('7');
});

it('requires a configured service', function (bool $locker): void {
    (new PostAwbRequestBuilder(12, null, null, null))->awb(shipmentData($locker ? ['pickupPointId' => '101'] : []));
})->with([false, true])->throws(ShipmentValidationException::class, 'Lipsește serviciul Sameday');

it('rejects options Sameday does not support', function (array $overrides): void {
    builder()->awb(shipmentData($overrides));
})->with([
    [['saturdayDelivery' => true]],
    [['sender' => new AddressData('Firma', '0700000000', 'Cluj', 'Cluj', 'Str. Y', '2')]],
])->throws(UnsupportedOperationException::class);
