<?php

declare(strict_types=1);

namespace WeBRTeu\Sameday;

use Illuminate\Support\Facades\Config;
use Sameday\Sameday;
use Sameday\SamedayClient;
use WeBRTeu\Sameday\Http\LaravelHttpClient;
use WeBRTeu\Sameday\Persistence\LaravelCachePersistentData;

class SamedayClientFactory
{
    /**
     * ⚠️ Doar RO e confirmat; BG/HU urmează schema publică Sameday, neverificate pe API real.
     */
    private const HOSTS = [
        'RO' => ['sandbox' => 'https://sameday-api.demo.zitec.com', 'production' => 'https://api.sameday.ro'],
        'BG' => ['sandbox' => 'https://sameday-api-bg.demo.zitec.com', 'production' => 'https://api.sameday.bg'],
        'HU' => ['sandbox' => 'https://sameday-api-hu.demo.zitec.com', 'production' => 'https://api.sameday.hu'],
    ];

    public function make(): Sameday
    {
        $username = (string) Config::get('sameday.username', '');

        return new Sameday(new SamedayClient(
            $username,
            (string) Config::get('sameday.password', ''),
            $this->host(),
            'webrteu-laravel',
            '1.0',
            new LaravelHttpClient(Config::integer('sameday.timeout', 20)),
            new LaravelCachePersistentData($username, Config::integer('sameday.token_cache_ttl', 21600)),
        ));
    }

    public function host(): string
    {
        $baseUrl = Config::get('sameday.base_url');

        if (is_string($baseUrl) && $baseUrl !== '') {
            return rtrim($baseUrl, '/');
        }

        $country = strtoupper(Config::string('sameday.country', 'RO'));
        $hosts = self::HOSTS[$country] ?? self::HOSTS['RO'];

        return Config::boolean('sameday.sandbox', true) ? $hosts['sandbox'] : $hosts['production'];
    }
}
