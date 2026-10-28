<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('Madgeek\LaravelShiprocket')
    ->toUseStrictTypes();

arch('services talk to shiprocket only through delivery')
    ->expect('Madgeek\LaravelShiprocket\Services')
    ->not->toUse('Illuminate\Http\Client');
