<?php

declare(strict_types=1);

namespace WeBRTeu\Sameday;

use WeBRTeu\Shipping\Enums\ShipmentStatus;
use WeBRTeu\Shipping\Support\StatusResolver;

/**
 * Codurile `statusId` Sameday → ShipmentStatus.
 *
 * ⚠️ Neverificat pe API real: maparea pornește de la etichetele publice ale statusurilor Sameday și se completează
 * din getAwbStatusHistory pe sandbox. Codurile lipsă cad pe cuvintele cheie din StatusResolver; ce rămâne Unknown
 * se loghează (`shipping.unknown_status`) și se poate suprascrie din `shipping.status_map.sameday`.
 */
final class SamedayStatusMap
{
    /**
     * @var array<array-key, ShipmentStatus>
     */
    public const MAP = [];

    public static function resolve(string $code, string $label): ShipmentStatus
    {
        return StatusResolver::resolve('sameday', $code, $label, self::MAP);
    }
}
