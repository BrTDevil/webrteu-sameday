<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use WeBRTeu\Sameday\Models\SamedayLocker;
use WeBRTeu\Sameday\Rules\SamedayLocker as SamedayLockerRule;

it('accepts only existing, active lockers', function (mixed $value, bool $passes): void {
    SamedayLocker::query()->create(['locker_id' => 101, 'name' => 'A', 'county' => 'Brașov', 'city' => 'Brașov', 'address' => 'x']);
    SamedayLocker::query()->create(['locker_id' => 102, 'name' => 'B', 'county' => 'Brașov', 'city' => 'Brașov', 'address' => 'x', 'active' => false]);

    $validator = Validator::make(['locker' => $value], ['locker' => [new SamedayLockerRule]]);

    expect($validator->passes())->toBe($passes);

    if (! $passes) {
        expect($validator->errors()->first('locker'))->toBe('Lockerul easybox ales nu există sau nu mai este activ.');
    }
})->with([
    ['101', true],
    [101, true],
    ['102', false],
    ['999', false],
    ['abc', false],
    [null, false],
]);
