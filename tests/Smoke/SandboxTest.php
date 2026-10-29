<?php

declare(strict_types=1);

use WeBRTeu\Shipping\Enums\LabelFormat;
use WeBRTeu\Shipping\Facades\Shipping;

it('creates, prints and deletes an AWB on the real sandbox', function (): void {
    $carrier = Shipping::carrier('sameday');

    $awb = $carrier->createShipment(shipmentData(['reference' => 'SMOKE-'.time()]));

    expect($awb->awb)->not->toBeEmpty()
        ->and($carrier->label($awb->awb, LabelFormat::A6Pdf))->toStartWith('%PDF')
        ->and($carrier->cancel($awb->awb))->toBeTrue();
})->group('sandbox')->skip(! env('SAMEDAY_SANDBOX_USERNAME'), 'Setează SAMEDAY_SANDBOX_USERNAME / SAMEDAY_SANDBOX_PASSWORD.');
