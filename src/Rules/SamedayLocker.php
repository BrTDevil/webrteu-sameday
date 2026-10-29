<?php

declare(strict_types=1);

namespace WeBRTeu\Sameday\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use WeBRTeu\Sameday\Models\SamedayLocker as Locker;

/**
 * Validează server-side lockerId-ul primit din checkout: există în sameday_lockers și e activ.
 */
class SamedayLocker implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = is_numeric($value) && Locker::query()->active()->where('locker_id', (int) $value)->exists();

        if (! $exists) {
            $fail('Lockerul easybox ales nu există sau nu mai este activ.');
        }
    }
}
