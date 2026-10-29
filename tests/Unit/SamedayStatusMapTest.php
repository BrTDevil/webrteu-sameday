<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use WeBRTeu\Sameday\SamedayStatusMap;
use WeBRTeu\Shipping\Enums\ShipmentStatus;

it('maps Sameday labels to normalized statuses', function (string $label, ShipmentStatus $expected): void {
    expect(SamedayStatusMap::resolve('1', $label))->toBe($expected);
})->with([
    ['Livrat cu succes', ShipmentStatus::Delivered],
    ['Colet în tranzit spre depozit', ShipmentStatus::InTransit],
    ['Depus în easybox', ShipmentStatus::AtPickupPoint],
    ['Colet preluat de curier', ShipmentStatus::PickedUp],
    ['În livrare la curier', ShipmentStatus::OutForDelivery],
    ['Retur la expeditor', ShipmentStatus::Returning],
    ['AWB anulat', ShipmentStatus::Cancelled],
]);

it('honours the application override for a Sameday code', function (): void {
    Config::set('shipping.status_map.sameday.84', 'returned');

    expect(SamedayStatusMap::resolve('84', 'Ceva'))->toBe(ShipmentStatus::Returned);
});

it('returns Unknown and logs an unknown code', function (): void {
    Log::spy();

    expect(SamedayStatusMap::resolve('999', 'Cod nou'))->toBe(ShipmentStatus::Unknown);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $message === 'shipping.unknown_status' && $context['code'] === '999');
});
