<?php

declare(strict_types=1);

namespace WeBRTeu\Sameday\Persistence;

use Illuminate\Support\Facades\Cache;
use Sameday\PersistentData\SamedayPersistentDataInterface;

/**
 * Păstrează token-ul SDK-ului în Cache sub cheia `sameday:token:{username}`,
 * ca fiecare cerere web să nu facă un login în plus.
 */
class LaravelCachePersistentData implements SamedayPersistentDataInterface
{
    public function __construct(
        private readonly string $username,
        private readonly int $ttl,
    ) {}

    public function cacheKey(): string
    {
        return 'sameday:token:'.$this->username;
    }

    /**
     * @param  string  $key
     */
    public function get($key): mixed
    {
        $data = Cache::get($this->cacheKey());

        return is_array($data) ? ($data[$key] ?? null) : null;
    }

    /**
     * @param  string  $key
     * @param  mixed  $value
     */
    public function set($key, $value): void
    {
        $data = Cache::get($this->cacheKey());
        $data = is_array($data) ? $data : [];

        if ($value === null) {
            unset($data[$key]);
        } else {
            $data[$key] = $value;
        }

        if ($data === []) {
            Cache::forget($this->cacheKey());

            return;
        }

        Cache::put($this->cacheKey(), $data, $this->ttl);
    }
}
