<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Sameday\SamedayCarrier;
use WeBRTeu\Sameday\SamedayClientFactory;
use WeBRTeu\Shipping\Facades\Shipping;

it('authenticates only once for two consecutive calls, even across instances', function (): void {
    fakeSameday(['GET /api/client/awb/1ONB24000001/status' => Http::response(fixtureFile('awb-status-history.json'))]);

    Shipping::carrier('sameday')->track('1ONB24000001');
    (new SamedayCarrier(new SamedayClientFactory))->track('1ONB24000001');

    Http::assertSentCount(3);
    expect(collect(Http::recorded())->filter(fn (array $pair): bool => str_ends_with($pair[0]->url(), '/api/authenticate')))->toHaveCount(1);
});

it('re-authenticates once after a 401 and retries the request', function (): void {
    fakeSameday([
        'POST /api/authenticate' => Http::sequence()
            ->push(['token' => 'old', 'expire_at' => '2099-01-01 10:00'])
            ->push(['token' => 'new', 'expire_at' => '2099-01-01 10:00']),
        'GET /api/client/awb/1ONB24000001/status' => fn (Request $request) => $request->hasHeader('X-AUTH-TOKEN', 'old')
            ? Http::response(['message' => 'expired'], 401)
            : Http::response(fixtureFile('awb-status-history.json')),
    ]);

    expect(Shipping::carrier('sameday')->track('1ONB24000001')->awb)->toBe('1ONB24000001');
    Http::assertSentCount(4);
});

it('never sends the password anywhere but the authentication request', function (): void {
    fakeSameday(['GET /api/client/awb/1ONB24000001/status' => Http::response(fixtureFile('awb-status-history.json'))]);

    Shipping::carrier('sameday')->track('1ONB24000001');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/authenticate')
        && $request->hasHeader('X-AUTH-USERNAME', 'test-user')
        && $request->hasHeader('X-AUTH-PASSWORD', 'test-pass'));
    Http::assertNotSent(fn (Request $request): bool => ! str_ends_with($request->url(), '/api/authenticate')
        && str_contains(json_encode($request->headers()).$request->body(), 'test-pass'));
});
