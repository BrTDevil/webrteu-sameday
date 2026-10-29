<?php

declare(strict_types=1);

namespace WeBRTeu\Sameday\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use WeBRTeu\Shipping\Data\PickupPointData;
use WeBRTeu\Shipping\Enums\PickupPointType;

/**
 * @property int $id
 * @property int $locker_id
 * @property string $name
 * @property string $county
 * @property string $city
 * @property string $address
 * @property string|null $postal_code
 * @property float|null $lat
 * @property float|null $lng
 * @property array<array-key, mixed>|null $schedule
 * @property bool $supports_cod
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SamedayLocker extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Config::string('sameday.tables.lockers', 'sameday_lockers');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locker_id' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
            'schedule' => 'array',
            'supports_cod' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function toPickupPoint(): PickupPointData
    {
        return new PickupPointData(
            id: (string) $this->locker_id,
            carrier: 'sameday',
            type: PickupPointType::Locker,
            name: $this->name,
            county: $this->county,
            city: $this->city,
            address: $this->address,
            lat: $this->lat,
            lng: $this->lng,
            postalCode: $this->postal_code,
            supportsCod: $this->supports_cod,
            schedule: $this->schedule,
        );
    }
}
