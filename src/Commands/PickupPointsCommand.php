<?php

declare(strict_types=1);

namespace WeBRTeu\Sameday\Commands;

use Illuminate\Console\Command;
use WeBRTeu\Sameday\SamedayCarrier;

class PickupPointsCommand extends Command
{
    protected $signature = 'sameday:pickup-points';

    protected $description = 'Afișează punctele de ridicare și persoanele de contact ale contului Sameday';

    public function handle(SamedayCarrier $sameday): int
    {
        $rows = [];

        foreach ($sameday->rawPickupPoints() as $point) {
            $contacts = is_array($point['pickupPointContactPerson'] ?? null) ? $point['pickupPointContactPerson'] : [];

            $rows[] = [
                is_scalar($point['id'] ?? null) ? (string) $point['id'] : '',
                is_scalar($point['alias'] ?? null) ? (string) $point['alias'] : '',
                is_scalar($point['address'] ?? null) ? (string) $point['address'] : '',
                ($point['defaultPickupPoint'] ?? false) === true ? 'da' : 'nu',
                implode(', ', array_map(
                    fn (mixed $contact): string => is_array($contact) && is_scalar($contact['id'] ?? null) && is_scalar($contact['name'] ?? null)
                        ? $contact['id'].': '.$contact['name']
                        : '',
                    $contacts,
                )),
            ];
        }

        $this->table(['Id', 'Alias', 'Adresă', 'Implicit', 'Persoane de contact (id: nume)'], $rows);

        return self::SUCCESS;
    }
}
