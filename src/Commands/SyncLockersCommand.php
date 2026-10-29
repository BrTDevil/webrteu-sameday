<?php

declare(strict_types=1);

namespace WeBRTeu\Sameday\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use WeBRTeu\Sameday\Models\SamedayLocker;
use WeBRTeu\Sameday\SamedayCarrier;

class SyncLockersCommand extends Command
{
    protected $signature = 'sameday:sync-lockers';

    protected $description = 'Sincronizează lockerele easybox Sameday în tabela sameday_lockers';

    public function handle(SamedayCarrier $sameday): int
    {
        $startedAt = Carbon::now()->subSecond();
        $synced = 0;

        foreach ($sameday->lockers() as $locker) {
            $point = SamedayCarrier::pickupPoint($locker);

            SamedayLocker::query()->updateOrCreate(['locker_id' => (int) $point->id], [
                'name' => $point->name,
                'county' => $point->county,
                'city' => $point->city,
                'address' => $point->address,
                'postal_code' => $point->postalCode,
                'lat' => $point->lat,
                'lng' => $point->lng,
                'schedule' => $point->schedule,
                'supports_cod' => $point->supportsCod,
                'active' => true,
            ])->touch();

            $synced++;
        }

        $deactivated = $synced === 0 ? 0 : SamedayLocker::query()
            ->where('active', true)
            ->where('updated_at', '<', $startedAt)
            ->update(['active' => false]);

        $this->info("Lockere sincronizate: {$synced}, dezactivate: {$deactivated}.");

        return self::SUCCESS;
    }
}
