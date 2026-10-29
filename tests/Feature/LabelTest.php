<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use WeBRTeu\Shipping\Enums\LabelFormat;
use WeBRTeu\Shipping\Exceptions\UnsupportedOperationException;
use WeBRTeu\Shipping\Facades\Shipping;

it('downloads the label as PDF bytes', function (LabelFormat $format, string $type): void {
    fakeSameday(["GET /api/awb/download/1ONB24000001/{$type}" => Http::response(fixtureFile('awb.pdf'), 200, ['Content-Type' => 'application/pdf'])]);

    expect(Shipping::carrier('sameday')->label('1ONB24000001', $format))->toStartWith('%PDF');

    Http::assertSent(fn (Request $request): bool => $request->url() === SAMEDAY_HOST."/api/awb/download/1ONB24000001/{$type}");
})->with([
    [LabelFormat::A6Pdf, 'A6'],
    [LabelFormat::A4Pdf, 'A4'],
]);

it('does not support ZPL labels', function (): void {
    Shipping::carrier('sameday')->label('1ONB24000001', LabelFormat::Zpl);
})->throws(UnsupportedOperationException::class);

it('cancels an AWB and reports a missing one', function (): void {
    fakeSameday([
        'DELETE /api/awb/1ONB24000001' => Http::response('', 200),
        'DELETE /api/awb/MISSING' => Http::response(['message' => 'Not found'], 404),
    ]);

    expect(Shipping::carrier('sameday')->cancel('1ONB24000001'))->toBeTrue()
        ->and(Shipping::carrier('sameday')->cancel('MISSING'))->toBeFalse();
});
