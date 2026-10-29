<?php

declare(strict_types=1);

namespace WeBRTeu\Sameday\Http;

use Illuminate\Support\Facades\Http;
use Sameday\Http\SamedayRawResponse;
use Sameday\HttpClients\SamedayHttpClientInterface;

/**
 * Clientul HTTP al SDK-ului Sameday peste Http:: din Laravel, ca Http::fake() să funcționeze în teste.
 * Fără retry: SDK-ul reîncearcă singur doar o dată, la 401/403, după re-autentificare.
 */
class LaravelHttpClient implements SamedayHttpClientInterface
{
    public function __construct(private readonly int $timeout = 20) {}

    /**
     * @param  string  $url
     * @param  string  $method
     * @param  string  $body
     * @param  array<string, string>  $headers
     * @param  int|null  $timeOut
     */
    public function send($url, $method, $body, array $headers, $timeOut): SamedayRawResponse
    {
        $request = Http::withHeaders($headers)->timeout(max((int) $timeOut, $this->timeout));

        if ($body !== '') {
            $request = $request->withBody($body, 'application/x-www-form-urlencoded');
        }

        $response = $request->send(strtoupper($method), $url);

        return new SamedayRawResponse(
            array_map(fn (array $values): string => implode(', ', $values), $response->headers()),
            $response->body(),
            $response->status(),
        );
    }
}
