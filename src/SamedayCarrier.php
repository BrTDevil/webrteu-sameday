<?php

declare(strict_types=1);

namespace WeBRTeu\Sameday;

use Carbon\CarbonImmutable;
use Closure;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Sameday\Exceptions\SamedayBadRequestException;
use Sameday\Exceptions\SamedayNotFoundException;
use Sameday\Exceptions\SamedaySDKException;
use Sameday\Http\SamedayRawResponse;
use Sameday\Objects\Types\AwbPdfType;
use Sameday\Requests\SamedayDeleteAwbRequest;
use Sameday\Requests\SamedayGetAwbPdfRequest;
use Sameday\Requests\SamedayGetAwbStatusHistoryRequest;
use Sameday\Requests\SamedayGetLockersRequest;
use Sameday\Requests\SamedayGetPickupPointsRequest;
use Sameday\Requests\SamedayGetServicesRequest;
use Sameday\Requests\SamedayGetStatusSyncRequest;
use Sameday\Sameday;
use WeBRTeu\Sameday\Exceptions\SamedayException;
use WeBRTeu\Sameday\Support\PostAwbRequestBuilder;
use WeBRTeu\Shipping\Contracts\Carrier;
use WeBRTeu\Shipping\Contracts\HasBulkTracking;
use WeBRTeu\Shipping\Contracts\HasPickupPoints;
use WeBRTeu\Shipping\Data\AwbData;
use WeBRTeu\Shipping\Data\PickupPointData;
use WeBRTeu\Shipping\Data\QuoteData;
use WeBRTeu\Shipping\Data\ServiceData;
use WeBRTeu\Shipping\Data\ShipmentData;
use WeBRTeu\Shipping\Data\TrackingData;
use WeBRTeu\Shipping\Data\TrackingEventData;
use WeBRTeu\Shipping\Enums\LabelFormat;
use WeBRTeu\Shipping\Enums\PickupPointType;
use WeBRTeu\Shipping\Exceptions\ShipmentStateUnknownException;
use WeBRTeu\Shipping\Exceptions\ShipmentValidationException;
use WeBRTeu\Shipping\Exceptions\UnsupportedOperationException;
use WeBRTeu\Shipping\Support\Money;
use WeBRTeu\Shipping\Support\Normalizer;

class SamedayCarrier implements Carrier, HasBulkTracking, HasPickupPoints
{
    /**
     * Codul taxei „deschidere colet” din serviceOptionalTaxes. ⚠️ neverificat pe API real.
     */
    private const OPEN_PACKAGE_TAX = 'OPCG';

    private ?Sameday $sdk = null;

    /**
     * @var list<array<string, mixed>>|null
     */
    private ?array $servicesCache = null;

    public function __construct(private readonly SamedayClientFactory $factory) {}

    public function name(): string
    {
        return 'sameday';
    }

    public function sdk(): Sameday
    {
        return $this->sdk ??= $this->factory->make();
    }

    public function services(): Collection
    {
        return collect($this->rawServices())->map(fn (array $service): ServiceData => new ServiceData(
            code: (string) self::int($service, 'id'),
            name: self::string($service, 'name'),
            pickupPoint: $this->isLockerService($service),
            raw: $service,
        ))->values();
    }

    public function quote(ShipmentData $shipment): QuoteData
    {
        $builder = $this->builder();
        $json = $this->call(fn (): SamedayRawResponse => $this->sdk()->postAwbEstimation($builder->estimation($shipment))->getRawResponse());

        return new QuoteData(
            carrier: $this->name(),
            amount: Money::fromDecimal($this->decimal($json, 'amount')),
            currency: self::string($json, 'currency', 'RON'),
            serviceCode: (string) $builder->serviceId($shipment),
            raw: $json,
        );
    }

    public function createShipment(ShipmentData $shipment): AwbData
    {
        $request = $this->builder()->awb($shipment, $shipment->openPackage ? $this->openPackageTax() : []);

        try {
            $json = $this->call(fn (): SamedayRawResponse => $this->sdk()->postAwb($request)->getRawResponse());
        } catch (ConnectionException $exception) {
            throw new ShipmentStateUnknownException($this->name(), $shipment->reference, $exception);
        }

        $parcels = is_array($json['parcels'] ?? null) ? $json['parcels'] : [];

        return new AwbData(
            awb: self::string($json, 'awbNumber'),
            carrier: $this->name(),
            cost: isset($json['awbCost']) ? Money::fromDecimal($this->decimal($json, 'awbCost')) : null,
            parcelNumbers: array_values(array_map(fn (mixed $parcel): string => is_array($parcel) ? self::string($parcel, 'awbNumber') : '', $parcels)),
            raw: $json,
        );
    }

    public function label(string $awb, LabelFormat $format = LabelFormat::A6Pdf): string
    {
        $type = match ($format) {
            LabelFormat::A6Pdf => AwbPdfType::A6,
            LabelFormat::A4Pdf => AwbPdfType::A4,
            LabelFormat::Zpl => throw UnsupportedOperationException::for($this->name(), 'label ZPL'),
        };

        return $this->send(fn (): SamedayRawResponse => $this->sdk()->getAwbPdf(new SamedayGetAwbPdfRequest($awb, new AwbPdfType($type)))->getRawResponse())->getBody();
    }

    public function track(string $awb): TrackingData
    {
        $json = $this->call(fn (): SamedayRawResponse => $this->sdk()->getAwbStatusHistory(new SamedayGetAwbStatusHistoryRequest($awb))->getRawResponse());
        $history = is_array($json['expeditionHistory'] ?? null) ? $json['expeditionHistory'] : [];

        $tracking = TrackingData::fromEvents($awb, array_values(array_map(
            fn (mixed $event): TrackingEventData => $this->event(is_array($event) ? $event : []),
            $history,
        )), $json);

        $summary = is_array($json['expeditionSummary'] ?? null) ? $json['expeditionSummary'] : [];
        $deliveredAt = $this->date($summary, 'deliveredAt');

        if (! $tracking->deliveredAt instanceof CarbonImmutable && $deliveredAt instanceof CarbonImmutable) {
            return new TrackingData($tracking->awb, $tracking->status, $tracking->statusLabel, $tracking->events, $deliveredAt, $tracking->raw);
        }

        return $tracking;
    }

    public function cancel(string $awb): bool
    {
        try {
            $this->send(fn (): SamedayRawResponse => $this->sdk()->deleteAwb(new SamedayDeleteAwbRequest($awb))->getRawResponse());
        } catch (SamedayNotFoundException) {
            return false;
        }

        return true;
    }

    public function pickupPoints(?string $county = null, ?string $city = null): Collection
    {
        return collect(iterator_to_array($this->lockers(), false))
            ->map(fn (array $locker): PickupPointData => self::pickupPoint($locker))
            ->filter(fn (PickupPointData $point): bool => ($county === null || Normalizer::same($point->county, $county))
                && ($city === null || Normalizer::same($point->city, $city)))
            ->values();
    }

    /**
     * Toate lockerele, pagină cu pagină.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function lockers(): Generator
    {
        $page = 1;

        do {
            $request = new SamedayGetLockersRequest;
            $request->setPage($page);
            $request->setCountPerPage(500);

            $json = $this->call(fn (): SamedayRawResponse => $this->sdk()->getLockers($request)->getRawResponse());

            foreach (is_array($json['data'] ?? null) ? $json['data'] : [] as $locker) {
                if (is_array($locker)) {
                    /** @var array<string, mixed> $locker */
                    yield $locker;
                }
            }

            $pages = self::int($json, 'pages', 1);
            $page++;
        } while ($page <= $pages);
    }

    /**
     * Punctele de ridicare ale contului (expeditorul).
     *
     * @return list<array<string, mixed>>
     */
    public function rawPickupPoints(): array
    {
        $request = new SamedayGetPickupPointsRequest;
        $request->setCountPerPage(500);
        $json = $this->call(fn (): SamedayRawResponse => $this->sdk()->getPickupPoints($request)->getRawResponse());

        return $this->rows($json);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rawServices(): array
    {
        if ($this->servicesCache === null) {
            $request = new SamedayGetServicesRequest;
            $request->setCountPerPage(500);

            $this->servicesCache = $this->rows($this->call(fn (): SamedayRawResponse => $this->sdk()->getServices($request)->getRawResponse()));
        }

        return $this->servicesCache;
    }

    /**
     * Folosește getStatusSync pe ultima fereastră de timp. ⚠️ limita maximă a ferestrei e de verificat cu Sameday.
     */
    public function trackMany(array $awbs): Collection
    {
        $end = CarbonImmutable::now();
        $start = $end->subMinutes(Config::integer('sameday.status_sync_window_minutes', 180));
        $events = [];
        $page = 1;

        do {
            $request = new SamedayGetStatusSyncRequest($start->getTimestamp(), $end->getTimestamp());
            $request->setPage($page);

            $json = $this->call(fn (): SamedayRawResponse => $this->sdk()->getStatusSync($request)->getRawResponse());

            foreach ($this->rows($json) as $status) {
                $awb = $this->matchAwb(self::string($status, 'parcelAwbNumber'), $awbs);

                if ($awb !== null) {
                    $events[$awb][] = $this->event($status);
                }
            }

            $pages = self::int($json, 'pages', 1);
            $page++;
        } while ($page <= $pages);

        /** @var Collection<string, TrackingData> $results */
        $results = collect();

        foreach ($events as $awb => $list) {
            $results->put($awb, TrackingData::fromEvents($awb, $list));
        }

        return $results;
    }

    public function builder(): PostAwbRequestBuilder
    {
        return new PostAwbRequestBuilder(
            pickupPointId: $this->pickupPointId(),
            contactPersonId: $this->nullableInt(Config::get('sameday.contact_person_id')),
            defaultService: $this->nullableInt(Config::get('sameday.default_service')),
            lockerService: $this->nullableInt(Config::get('sameday.locker_service')),
        );
    }

    private function pickupPointId(): int
    {
        $configured = $this->nullableInt(Config::get('sameday.pickup_point_id'));

        if ($configured !== null) {
            return $configured;
        }

        $points = $this->rawPickupPoints();
        $default = collect($points)->first(fn (array $point): bool => ($point['defaultPickupPoint'] ?? false) === true) ?? ($points[0] ?? null);

        if (! is_array($default)) {
            throw new SamedayException('Contul Sameday nu are niciun punct de ridicare. Setează SAMEDAY_PICKUP_POINT_ID.');
        }

        return self::int($default, 'id');
    }

    /**
     * @return list<int>
     */
    private function openPackageTax(): array
    {
        foreach ($this->rawServices() as $service) {
            foreach (is_array($service['serviceOptionalTaxes'] ?? null) ? $service['serviceOptionalTaxes'] : [] as $tax) {
                if (is_array($tax) && ($tax['taxCode'] ?? null) === self::OPEN_PACKAGE_TAX) {
                    return [self::int($tax, 'id')];
                }
            }
        }

        throw UnsupportedOperationException::for($this->name(), 'openPackage (contul nu are taxa '.self::OPEN_PACKAGE_TAX.')');
    }

    /**
     * @param  list<string>  $awbs
     */
    private function matchAwb(string $parcelAwb, array $awbs): ?string
    {
        foreach ($awbs as $awb) {
            if ($awb !== '' && str_starts_with($parcelAwb, $awb)) {
                return $awb;
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $event
     */
    private function event(array $event): TrackingEventData
    {
        $code = (string) self::int($event, 'statusId');
        $label = self::string($event, 'statusLabel') !== '' ? self::string($event, 'statusLabel') : self::string($event, 'status');
        $location = self::string($event, 'transitLocation') !== '' ? self::string($event, 'transitLocation') : self::string($event, 'county');

        return new TrackingEventData(
            status: SamedayStatusMap::resolve($code, $label),
            code: $code,
            label: $label,
            occurredAt: $this->date($event, 'statusDate'),
            location: $location !== '' ? $location : null,
            raw: $event,
        );
    }

    /**
     * @param  array<array-key, mixed>  $locker
     */
    public static function pickupPoint(array $locker): PickupPointData
    {
        $schedule = is_array($locker['schedule'] ?? null) ? $locker['schedule'] : null;

        return new PickupPointData(
            id: (string) self::int($locker, 'lockerId'),
            carrier: 'sameday',
            type: PickupPointType::Locker,
            name: self::string($locker, 'name'),
            county: self::string($locker, 'county'),
            city: self::string($locker, 'city'),
            address: self::string($locker, 'address'),
            lat: is_numeric($locker['lat'] ?? null) ? (float) $locker['lat'] : null,
            lng: is_numeric($locker['lng'] ?? null) ? (float) $locker['lng'] : null,
            postalCode: self::string($locker, 'postalCode') !== '' ? self::string($locker, 'postalCode') : null,
            supportsCod: self::supportsCod($locker),
            schedule: $schedule,
            raw: $locker,
        );
    }

    /**
     * @param  array<array-key, mixed>  $locker
     */
    private static function supportsCod(array $locker): bool
    {
        $payment = $locker['supportedPayment'] ?? null;

        return $payment === true || (is_numeric($payment) && (int) $payment > 0) || (is_string($payment) && str_contains(strtolower($payment), 'card'));
    }

    /**
     * @param  array<array-key, mixed>  $service
     */
    private function isLockerService(array $service): bool
    {
        $type = is_array($service['deliveryType'] ?? null) ? self::string($service['deliveryType'], 'name') : '';
        $haystack = strtolower($type.' '.self::string($service, 'name').' '.self::string($service, 'serviceCode'));

        return str_contains($haystack, 'locker') || str_contains($haystack, 'easybox');
    }

    /**
     * Trimite cererea și decodează JSON-ul.
     *
     * @param  Closure(): SamedayRawResponse  $request
     * @return array<array-key, mixed>
     */
    private function call(Closure $request): array
    {
        $decoded = json_decode($this->send($request)->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  Closure(): SamedayRawResponse  $request
     */
    private function send(Closure $request): SamedayRawResponse
    {
        try {
            return $request();
        } catch (SamedayBadRequestException $exception) {
            throw ShipmentValidationException::withErrors($this->name(), $this->errors($exception), $exception);
        } catch (SamedayNotFoundException $exception) {
            throw $exception;
        } catch (SamedaySDKException $exception) {
            throw new SamedayException('Sameday: '.$exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function errors(SamedayBadRequestException $exception): array
    {
        $errors = [];

        foreach ($exception->getErrors() as $error) {
            $field = implode('.', array_map(strval(...), (array) ($error['key'] ?? [])));
            $errors[$field !== '' ? $field : 'general'] = array_values(array_map(strval(...), (array) ($error['errors'] ?? [])));
        }

        return $errors !== [] ? $errors : ['general' => [$exception->getMessage()]];
    }

    /**
     * @param  array<array-key, mixed>  $json
     * @return list<array<string, mixed>>
     */
    private function rows(array $json): array
    {
        $rows = [];

        foreach (is_array($json['data'] ?? null) ? $json['data'] : [] as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function int(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Sumele vin ca float în JSON; le formatăm cu două zecimale fără aritmetică.
     *
     * @param  array<array-key, mixed>  $data
     */
    private function decimal(array $data, string $key): string
    {
        $value = $data[$key] ?? 0;

        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : '0';
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function date(array $data, string $key): ?CarbonImmutable
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
