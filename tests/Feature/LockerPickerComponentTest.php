<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('renders the official script once, the input name and passes wire:model on', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-sameday::locker-picker wire:model="lockerId" county="Brașov" city="Râșnov" />
        <x-sameday::locker-picker name="alt_locker" class="btn" />
        BLADE);

    expect(substr_count($html, 'lockerpluginsdk.js'))->toBe(1)
        ->and($html)->toContain('name="sameday_locker_id"')
        ->toContain('name="alt_locker"')
        ->toContain('wire:model="lockerId"')
        ->toContain('class="btn"')
        ->toContain('county')
        ->toContain('Bra\u0219ov')
        ->toContain('LockerPlugin.init');
});
