<?php

declare(strict_types=1);

use Madgeek\LaravelShiprocket\Credentials\Credentials;

it('builds credentials from config', function () {
    $credentials = Credentials::fromConfig([
        'email' => 'api@example.com',
        'password' => 'secret',
        'base_url' => 'https://apiv2.shiprocket.in/',
        'token_cache_key' => 'custom.key',
        'token_ttl' => '3600',
    ]);

    expect($credentials->email)->toBe('api@example.com')
        ->and($credentials->password)->toBe('secret')
        ->and($credentials->baseUrl)->toBe('https://apiv2.shiprocket.in')
        ->and($credentials->tokenCacheKey)->toBe('custom.key')
        ->and($credentials->tokenTtl)->toBe(3600)
        ->and($credentials->hasLogin())->toBeTrue()
        ->and($credentials->url('/v1/external/orders'))->toBe('https://apiv2.shiprocket.in/v1/external/orders');
});

it('falls back to defaults for missing or invalid values', function () {
    $credentials = Credentials::fromConfig([
        'email' => '',
        'password' => null,
        'token_ttl' => 'forever',
    ]);

    expect($credentials->email)->toBe('')
        ->and($credentials->baseUrl)->toBe('https://apiv2.shiprocket.in')
        ->and($credentials->tokenCacheKey)->toBe('shiprocket.token')
        ->and($credentials->tokenTtl)->toBe(777600)
        ->and($credentials->hasLogin())->toBeFalse()
        ->and(Credentials::fromConfig(['token_ttl' => 60])->tokenTtl)->toBe(60);
});
