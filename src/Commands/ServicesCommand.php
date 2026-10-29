<?php

declare(strict_types=1);

namespace WeBRTeu\Sameday\Commands;

use Illuminate\Console\Command;
use WeBRTeu\Sameday\SamedayCarrier;
use WeBRTeu\Shipping\Data\ServiceData;

class ServicesCommand extends Command
{
    protected $signature = 'sameday:services';

    protected $description = 'Afișează serviciile contului Sameday (id-uri pentru SAMEDAY_DEFAULT_SERVICE / SAMEDAY_LOCKER_SERVICE)';

    public function handle(SamedayCarrier $sameday): int
    {
        $this->table(['Id', 'Nume', 'Easybox'], $sameday->services()->map(fn (ServiceData $service): array => [
            $service->code,
            $service->name,
            $service->pickupPoint ? 'da' : 'nu',
        ])->all());

        return self::SUCCESS;
    }
}
