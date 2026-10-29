# webrteu/sameday

[![PHP](https://img.shields.io/badge/PHP-%5E8.2-777bb4)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12%E2%80%9313-ff2d20)](https://laravel.com/)
[![Licență MIT](https://img.shields.io/badge/licen%C8%9B%C4%83-MIT-green)](LICENSE.md)
[![Packagist](https://img.shields.io/packagist/v/webrteu/sameday)](https://packagist.org/packages/webrteu/sameday)

Integrare Laravel pentru Sameday Courier, inclusiv livrare în lockere **easybox**. Împachetează SDK-ul oficial
`sameday-courier/php-sdk` (nu rescrie API-ul) și implementează contractele din `webrteu/shipping`: AWB, etichete,
tracking, lockere, harta easybox în checkout.

Nu este un pachet oficial Sameday.

## Cerințe

- PHP `^8.2`, Laravel 12–13
- cont Sameday cu acces API (utilizator API, nu cel din eAWB)
- `webrteu/shipping`

## Instalare

```bash
composer require webrteu/sameday
php artisan vendor:publish --tag=sameday-config
php artisan vendor:publish --tag=shipping-migrations
php artisan vendor:publish --tag=sameday-migrations
php artisan migrate
```

## Configurare

```dotenv
SAMEDAY_USERNAME=          # utilizator API primit de la Sameday (nu cel din eAWB)
SAMEDAY_PASSWORD=
SAMEDAY_SANDBOX=true       # true = sameday-api.demo.zitec.com, cu credențiale de test separate
SAMEDAY_COUNTRY=RO         # RO | BG | HU
SAMEDAY_PICKUP_POINT_ID=   # gol = punctul de ridicare implicit al contului (php artisan sameday:pickup-points)
SAMEDAY_CONTACT_PERSON_ID=
SAMEDAY_DEFAULT_SERVICE=   # id serviciu standard (php artisan sameday:services)
SAMEDAY_LOCKER_SERVICE=    # id serviciu easybox
SAMEDAY_LM_USERNAME=       # pentru harta de lockere (Locker Plugin)
SAMEDAY_TIMEOUT=20         # secunde
```

Credențialele API se cer de la Sameday (account manager / suport integrări). Sandbox-ul are credențiale separate.

⚠️ URL-ul de producție `https://api.sameday.ro` și host-urile BG/HU sunt neverificate pe API real. Pot fi forțate cu `SAMEDAY_BASE_URL`.

## Utilizare

```php
use WeBRTeu\Shipping\Data\{AddressData, ParcelData, ShipmentData};
use WeBRTeu\Shipping\Facades\Shipping;
use WeBRTeu\Shipping\Models\Shipment;

$data = new ShipmentData(
    recipient: new AddressData(name: 'Ion Popescu', phone: '0721000000', county: 'Brașov', city: 'Râșnov', street: 'Str. Republicii', number: '10'),
    parcels: [new ParcelData(1.2)],
    reference: 'CMD-1024',
    codAmount: 14970,                              // 149,70 lei ramburs
    pickupPointId: $request->sameday_locker_id,    // null = livrare la adresă
);

$awb = Shipping::carrier('sameday')->createShipment($data);
Shipment::recordFrom($awb, $data, $order);

return response(Shipping::carrier('sameday')->label($awb->awb), 200, ['Content-Type' => 'application/pdf']);
```

| Metodă | SDK |
|---|---|
| `services()` | `getServices` |
| `quote($data)` | `postAwbEstimation` |
| `createShipment($data)` | `postAwb`. Fără retry; timeout → `ShipmentStateUnknownException`; 400 → `ShipmentValidationException` cu `$e->errors` pe câmpuri |
| `label($awb, LabelFormat::A6Pdf\|A4Pdf)` | `getAwbPdf` (ZPL nu e suportat) |
| `track($awb)` | `getAwbStatusHistory` |
| `cancel($awb)` | `deleteAwb` |
| `pickupPoints($county, $city)` | `getLockers` (toate paginile) |
| `trackMany($awbs)` | `getStatusSync` pe ultimele 180 de minute ⚠️ limita de interval e neverificată |

Particularități: rambursul se trimite în lei (conversie din bani); la easybox serviciul devine `SAMEDAY_LOCKER_SERVICE`,
iar lockerul se trimite în `lockerLastMile`; adresa trimisă rămâne a clientului. `openPackage` folosește taxa
opțională `OPCG` a contului ⚠️. Livrarea sâmbăta și expeditorul diferit de punctul de ridicare nu sunt suportate.

Facade-ul `Sameday` dă acces direct la carrier și la SDK: `Sameday::sdk()->getCounties(...)`.

## Easybox în checkout

```blade
<x-sameday::locker-picker wire:model="lockerId" county="Brașov" city="Râșnov" />
```

Încarcă o singură dată scriptul oficial `lockerpluginsdk.js`, deschide harta și pune `lockerId` în input
(`name="sameday_locker_id"` implicit; merge și cu Livewire). ⚠️ `clientId`-ul pluginului e cel public din documentația
Sameday. Se poate schimba cu `SAMEDAY_LM_CLIENT_ID`.

Validare server-side:

```php
use WeBRTeu\Sameday\Rules\SamedayLocker;

$request->validate(['sameday_locker_id' => ['nullable', new SamedayLocker]]);
```

## Comenzi Artisan

| Comandă | Ce face |
|---|---|
| `sameday:sync-lockers` | salvează lockerele în `sameday_lockers`; cele dispărute devin inactive |
| `sameday:services` | id-urile serviciilor contului |
| `sameday:pickup-points` | punctele de ridicare și persoanele de contact |
| `shipping:sync` | (din core) statusurile AWB-urilor, prin `trackMany` |

```php
Schedule::command('sameday:sync-lockers')->daily();
Schedule::command('shipping:sync --carrier=sameday')->hourly()->withoutOverlapping();
```

## Statusuri

⚠️ Maparea codurilor Sameday (`SamedayStatusMap`) e goală până la primele răspunsuri reale de pe sandbox; până atunci
statusul se deduce din eticheta în română („Livrat”, „tranzit”, „easybox”...). Codurile necunoscute se loghează
(`shipping.unknown_status`) și se pot mapa din aplicație:

```php
// config/shipping.php
'status_map' => ['sameday' => ['9' => 'delivered']],
```

## Ce nu face pachetul

- nu creează conturi Sameday și nu obține credențiale
- nu acoperă API-ul de retururi
- nu suportă livrarea sâmbăta și expeditori diferiți de punctele de ridicare ale contului

## Testare

```bash
composer test
SAMEDAY_SANDBOX_USERNAME=... SAMEDAY_SANDBOX_PASSWORD=... vendor/bin/pest --group=sandbox
```

Fixture-urile din `tests/Fixtures` sunt scrise după structura parsată de SDK. **De înlocuit cu răspunsuri reale** de pe sandbox.

## Changelog

Vezi [CHANGELOG.md](CHANGELOG.md).

## Autor

[WeBRTeu](https://webrt.eu)

## Licență

MIT. Vezi [LICENSE.md](LICENSE.md).
