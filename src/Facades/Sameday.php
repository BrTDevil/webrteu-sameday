<?php

declare(strict_types=1);

namespace WeBRTeu\Sameday\Facades;

use Illuminate\Support\Facades\Facade;
use WeBRTeu\Sameday\SamedayCarrier;

/**
 * @method static \Illuminate\Support\Collection<int, \WeBRTeu\Shipping\Data\ServiceData> services()
 * @method static \WeBRTeu\Shipping\Data\AwbData createShipment(\WeBRTeu\Shipping\Data\ShipmentData $shipment)
 * @method static \WeBRTeu\Shipping\Data\TrackingData track(string $awb)
 * @method static string label(string $awb, \WeBRTeu\Shipping\Enums\LabelFormat $format = \WeBRTeu\Shipping\Enums\LabelFormat::A6Pdf)
 * @method static \Illuminate\Support\Collection<int, \WeBRTeu\Shipping\Data\PickupPointData> pickupPoints(?string $county = null, ?string $city = null)
 * @method static \Sameday\Sameday sdk()
 *
 * @see SamedayCarrier
 */
class Sameday extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SamedayCarrier::class;
    }
}
