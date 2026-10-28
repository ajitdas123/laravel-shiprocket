<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Madgeek\LaravelShiprocket\Facades\Shiprocket;

it('logs requests instead of calling shiprocket', function () {
    Http::fake();
    $logs = captureLogs();

    $response = Shiprocket::couriers()->serviceability(['pickup_postcode' => 110030, 'delivery_postcode' => 122002]);

    expect($response->successful)->toBeTrue()
        ->and($response->message)->toBe('logged')
        ->and($logs)->toHaveCount(1)
        ->and($logs[0]->context['method'])->toBe('GET')
        ->and($logs[0]->context['endpoint'])->toBe('/v1/external/courier/serviceability/')
        ->and($logs[0]->context['query'])->toBe(['pickup_postcode' => 110030, 'delivery_postcode' => 122002]);

    Http::assertNothingSent();
});

it('does not log credentials for a login request', function () {
    $logs = captureLogs();

    expect(Shiprocket::auth()->getToken())->toBe('logged');

    Shiprocket::auth()->login();

    expect($logs[0]->context['payload'])->toBe([]);
});
