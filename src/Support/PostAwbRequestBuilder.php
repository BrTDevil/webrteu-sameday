<?php

declare(strict_types=1);

namespace WeBRTeu\Sameday\Support;

use Sameday\Objects\ParcelDimensionsObject;
use Sameday\Objects\PostAwb\Request\AwbRecipientEntityObject;
use Sameday\Objects\PostAwb\Request\CompanyEntityObject;
use Sameday\Objects\Types\AwbPaymentType;
use Sameday\Objects\Types\PackageType;
use Sameday\Requests\SamedayPostAwbEstimationRequest;
use Sameday\Requests\SamedayPostAwbRequest;
use WeBRTeu\Shipping\Data\AddressData;
use WeBRTeu\Shipping\Data\ParcelData;
use WeBRTeu\Shipping\Data\ShipmentData;
use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;
use WeBRTeu\Shipping\Exceptions\UnsupportedOperationException;
use WeBRTeu\Shipping\Support\Money;
use WeBRTeu\Shipping\Support\Normalizer;

/**
 * Transformă ShipmentData în cererile SDK-ului Sameday.
 */
final readonly class PostAwbRequestBuilder
{
    /**
     * Greutatea trimisă pentru un plic fără colete, în kg.
     */
    private const ENVELOPE_WEIGHT_KG = 0.1;

    public function __construct(
        private int $pickupPointId,
        private ?int $contactPersonId,
        private ?int $defaultService,
        private ?int $lockerService,
    ) {}

    /**
     * @param  list<int>  $serviceTaxIds
     */
    public function awb(ShipmentData $shipment, array $serviceTaxIds = []): SamedayPostAwbRequest
    {
        $this->guard($shipment);

        return new SamedayPostAwbRequest(
            pickupPointId: $this->pickupPointId,
            contactPersonId: $this->contactPersonId,
            packageType: $this->packageType($shipment),
            parcelsDimensions: $this->parcels($shipment),
            serviceId: $this->serviceId($shipment),
            awbPayment: new AwbPaymentType(AwbPaymentType::CLIENT),
            awbRecipient: $this->recipient($shipment->recipient),
            insuredValue: Money::toFloat($shipment->declaredValue),
            cashOnDeliveryAmount: Money::toFloat($shipment->codAmount),
            serviceTaxIds: $serviceTaxIds,
            reference: $shipment->reference,
            observation: $shipment->observations ?? ($shipment->contents !== '' ? $shipment->contents : null),
            clientObservation: $shipment->contents !== '' ? $shipment->contents : null,
            lockerLastMile: $shipment->isPickupPointDelivery() ? (int) $shipment->pickupPointId : null,
        );
    }

    public function estimation(ShipmentData $shipment): SamedayPostAwbEstimationRequest
    {
        $this->guard($shipment);

        return new SamedayPostAwbEstimationRequest(
            pickupPointId: $this->pickupPointId,
            contactPersonId: $this->contactPersonId,
            packageType: $this->packageType($shipment),
            parcelsDimensions: $this->parcels($shipment),
            serviceId: $this->serviceId($shipment),
            awbPayment: new AwbPaymentType(AwbPaymentType::CLIENT),
            awbRecipient: $this->recipient($shipment->recipient),
            insuredValue: Money::toFloat($shipment->declaredValue),
            cashOnDeliveryAmount: Money::toFloat($shipment->codAmount),
        );
    }

    public function serviceId(ShipmentData $shipment): int
    {
        $service = $shipment->serviceCode !== null && $shipment->serviceCode !== ''
            ? (int) $shipment->serviceCode
            : ($shipment->isPickupPointDelivery() ? $this->lockerService : $this->defaultService);

        if ($service === null || $service === 0) {
            throw new ShipmentValidationException($shipment->isPickupPointDelivery()
                ? 'Lipsește serviciul Sameday pentru easybox: setează SAMEDAY_LOCKER_SERVICE (vezi php artisan sameday:services).'
                : 'Lipsește serviciul Sameday: setează SAMEDAY_DEFAULT_SERVICE (vezi php artisan sameday:services).');
        }

        return $service;
    }

    /**
     * Adresa fără localitate și județ, care se trimit separat.
     */
    public static function streetLine(AddressData $address): string
    {
        return implode(', ', array_filter([
            trim($address->street.' nr. '.$address->number),
            $address->block !== null ? 'bl. '.$address->block : null,
            $address->staircase !== null ? 'sc. '.$address->staircase : null,
            $address->floor !== null ? 'et. '.$address->floor : null,
            $address->apartment !== null ? 'ap. '.$address->apartment : null,
        ]));
    }

    private function guard(ShipmentData $shipment): void
    {
        if ($shipment->sender instanceof AddressData) {
            throw UnsupportedOperationException::for('sameday', 'sender (expeditorul vine din SAMEDAY_PICKUP_POINT_ID)');
        }

        if ($shipment->saturdayDelivery) {
            throw UnsupportedOperationException::for('sameday', 'saturdayDelivery');
        }
    }

    private function packageType(ShipmentData $shipment): PackageType
    {
        return new PackageType($shipment->parcels === [] ? PackageType::ENVELOPE : PackageType::PARCEL);
    }

    /**
     * @return list<ParcelDimensionsObject>
     */
    private function parcels(ShipmentData $shipment): array
    {
        if ($shipment->parcels === []) {
            return array_fill(0, $shipment->envelopes, new ParcelDimensionsObject(self::ENVELOPE_WEIGHT_KG));
        }

        return array_map(
            fn (ParcelData $parcel): ParcelDimensionsObject => new ParcelDimensionsObject($parcel->weightKg, $parcel->widthCm, $parcel->lengthCm, $parcel->heightCm),
            $shipment->parcels,
        );
    }

    private function recipient(AddressData $address): AwbRecipientEntityObject
    {
        return new AwbRecipientEntityObject(
            city: $address->city,
            county: $address->county,
            address: self::streetLine($address),
            name: $address->contactPerson ?? $address->name,
            phone: Normalizer::phone($address->phone),
            email: $address->email,
            company: $address->company !== null ? new CompanyEntityObject($address->company) : null,
            postalCode: $address->postalCode,
        );
    }
}
