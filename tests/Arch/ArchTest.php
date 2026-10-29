<?php

declare(strict_types=1);
use WeBRTeu\Shipping\Exceptions\ShippingException;

arch('nu folosește funcții de debug')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('toate fișierele au strict types')
    ->expect('WeBRTeu\Sameday')
    ->toUseStrictTypes();

arch('fără Guzzle direct: HTTP doar prin Http::')
    ->expect('WeBRTeu\Sameday')
    ->not->toUse('GuzzleHttp');

arch('excepțiile extind ShippingException')
    ->expect('WeBRTeu\Sameday\Exceptions')
    ->toExtend(ShippingException::class);
