<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Sameday\Models\SamedayLocker;

function fakeLockers(int $times = 1): void
{
    $sequence = Http::sequence();

    for ($i = 0; $i < $times; $i++) {
        $sequence->push(fixtureFile('lockers-page-1.json'))->push(fixtureFile('lockers-page-2.json'));
    }

    fakeSameday(['GET /api/client/lockers' => $sequence]);
}

it('syncs every page of lockers', function (): void {
    fakeLockers();

    $this->artisan('sameday:sync-lockers')
        ->expectsOutput('Lockere sincronizate: 3, dezactivate: 0.')
        ->assertSuccessful();

    $locker = SamedayLocker::query()->where('locker_id', 101)->sole();

    expect(SamedayLocker::query()->count())->toBe(3)
        ->and($locker->city)->toBe('Brașov')
        ->and($locker->supports_cod)->toBeTrue()
        ->and($locker->schedule)->toBe([['day' => 1, 'openingHour' => '00:00', 'closingHour' => '23:59']])
        ->and($locker->toPickupPoint()->id)->toBe('101')
        ->and($locker->getTable())->toBe('sameday_lockers');
});

it('updates without duplicates and deactivates lockers that disappeared', function (): void {
    Carbon::setTestNow('2026-01-01 10:00:00');
    SamedayLocker::query()->create(['locker_id' => 999, 'name' => 'Vechi', 'county' => 'Iași', 'city' => 'Iași', 'address' => 'x']);
    fakeLockers(2);

    $this->artisan('sameday:sync-lockers');
    Carbon::setTestNow('2026-01-02 10:00:00');
    $this->artisan('sameday:sync-lockers')->expectsOutput('Lockere sincronizate: 3, dezactivate: 1.');

    expect(SamedayLocker::query()->count())->toBe(4)
        ->and(SamedayLocker::query()->where('locker_id', 999)->sole()->active)->toBeFalse()
        ->and(SamedayLocker::query()->active()->count())->toBe(3);
});

it('does not deactivate anything when the API returns no lockers', function (): void {
    SamedayLocker::query()->create(['locker_id' => 999, 'name' => 'Vechi', 'county' => 'Iași', 'city' => 'Iași', 'address' => 'x']);
    fakeSameday(['GET /api/client/lockers' => Http::response(['data' => [], 'currentPage' => 1, 'pages' => 1, 'perPage' => 500])]);

    $this->artisan('sameday:sync-lockers')->expectsOutput('Lockere sincronizate: 0, dezactivate: 0.');

    expect(SamedayLocker::query()->sole()->active)->toBeTrue();
});
