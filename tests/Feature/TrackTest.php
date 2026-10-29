<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Shipping\Enums\ShipmentStatus;
use WeBRTeu\Shipping\Facades\Shipping;
use WeBRTeu\Shipping\Models\Shipment;

it('turns the status history into TrackingData', function (): void {
    fakeSameday(['GET /api/client/awb/1ONB24000001/status' => Http::response(fixtureFile('awb-status-history.json'))]);

    $tracking = Shipping::carrier('sameday')->track('1ONB24000001');

    expect($tracking->status)->toBe(ShipmentStatus::Delivered)
        ->and($tracking->statusLabel)->toBe('Livrat cu succes')
        ->and($tracking->events)->toHaveCount(3)
        ->and($tracking->events[0]->status)->toBe(ShipmentStatus::Created)
        ->and($tracking->events[0]->location)->toBe('Brașov')
        ->and($tracking->events[1]->location)->toBe('Hub Brașov')
        ->and($tracking->deliveredAt?->equalTo(CarbonImmutable::parse('2026-01-03T12:10:00+02:00')))->toBeTrue();
});

it('takes deliveredAt from the summary when no Delivered event is present', function (): void {
    fakeSameday(['GET /api/client/awb/1ONB24000002/status' => Http::response(fixtureFile('awb-status-history-summary-only.json'))]);

    $tracking = Shipping::carrier('sameday')->track('1ONB24000002');

    expect($tracking->status)->toBe(ShipmentStatus::Created)
        ->and($tracking->deliveredAt?->toIso8601String())->toBe('2026-01-05T10:00:00+02:00');
});

it('tracks many AWBs through the paginated status sync', function (): void {
    CarbonImmutable::setTestNow('2026-01-03 13:00:00');
    fakeSameday(['GET /api/client/status-sync' => Http::sequence()
        ->push(fixtureFile('status-sync-page-1.json'))
        ->push(fixtureFile('status-sync-page-2.json'))]);

    $results = Shipping::carrier('sameday')->trackMany(['1ONB24000001', '1ONB24000003', '1ONB24000009', '']);

    expect($results->keys()->all())->toBe(['1ONB24000001', '1ONB24000003'])
        ->and($results['1ONB24000001']->status)->toBe(ShipmentStatus::Delivered)
        ->and($results['1ONB24000003']->status)->toBe(ShipmentStatus::AtPickupPoint);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'status-sync')
        && str_contains($request->url(), 'startTimestamp='.CarbonImmutable::now()->subMinutes(180)->getTimestamp()));
});

it('works with the shipping:sync command', function (): void {
    fakeSameday(['GET /api/client/status-sync' => Http::response(fixtureFile('status-sync-page-1.json'))]);
    Shipment::query()->create(['carrier' => 'sameday', 'awb' => '1ONB24000001', 'status' => ShipmentStatus::Created]);

    $this->artisan('shipping:sync')->expectsOutput('Verificate: 1, schimbate: 1, erori: 0.');
});
