<?php

declare(strict_types=1);

return [

    'username' => env('SAMEDAY_USERNAME'),
    'password' => env('SAMEDAY_PASSWORD'),

    /*
    | true = API-ul demo (sameday-api.demo.zitec.com), cu credențiale de test separate de producție.
    */
    'sandbox' => (bool) env('SAMEDAY_SANDBOX', true),

    /*
    | null = ales automat după sandbox și țară.
    */
    'base_url' => env('SAMEDAY_BASE_URL'),

    'country' => env('SAMEDAY_COUNTRY', 'RO'),

    /*
    | null = punctul de ridicare implicit al contului (php artisan sameday:pickup-points).
    */
    'pickup_point_id' => env('SAMEDAY_PICKUP_POINT_ID'),
    'contact_person_id' => env('SAMEDAY_CONTACT_PERSON_ID'),

    /*
    | Id-urile serviciilor (php artisan sameday:services).
    */
    'default_service' => env('SAMEDAY_DEFAULT_SERVICE'),
    'locker_service' => env('SAMEDAY_LOCKER_SERVICE'),

    'locker_plugin' => [
        'client_id' => env('SAMEDAY_LM_CLIENT_ID', 'b8cb2ee3-41b9-4c3d-aafe-1527b453d65e'),
        'lm_username' => env('SAMEDAY_LM_USERNAME'),
        'theme' => 'light',
        'script' => 'https://cdn.sameday.ro/locker-plugin/lockerpluginsdk.js',
    ],

    'token_cache_ttl' => 60 * 60 * 6,

    'timeout' => (int) env('SAMEDAY_TIMEOUT', 20),

    /*
    | Fereastra pentru getStatusSync (trackMany), în minute.
    */
    'status_sync_window_minutes' => 180,

    'tables' => [
        'lockers' => 'sameday_lockers',
    ],

];
