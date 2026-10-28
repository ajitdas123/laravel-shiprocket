<?php

declare(strict_types=1);

use Madgeek\LaravelShiprocket\Exceptions\ShiprocketException;
use Madgeek\LaravelShiprocket\Facades\Shiprocket;
use Madgeek\LaravelShiprocket\Testing\RecordedRequest;
use PHPUnit\Framework\AssertionFailedError;

it('returns stubbed response bodies by endpoint pattern', function () {
    Shiprocket::fake([
        '/v1/external/account/details/wallet-balance' => ['data' => ['balance_amount' => '1500.00']],
        '/v1/external/orders/*' => ['order_id' => 281248157, 'shipment_id' => 16090281],
    ]);

    $wallet = Shiprocket::account()->walletBalance();
    $order = Shiprocket::orders()->create(['order_id' => '224-447']);
    $couriers = Shiprocket::couriers()->list();

    expect($wallet->json('data.balance_amount'))->toBe('1500.00')
        ->and($order->json('shipment_id'))->toBe(16090281)
        ->and($order->successful)->toBeTrue()
        ->and($couriers->message)->toBe('faked');
});

it('fails assertions when no matching request was recorded', function () {
    Shiprocket::fake();

    Shiprocket::orders()->cancel(1);

    expect(fn () => Shiprocket::assertRequested('POST', '/v1/external/orders/cancel', fn (RecordedRequest $request): bool => $request->payload === ['ids' => [2]]))
        ->toThrow(AssertionFailedError::class)
        ->and(fn () => Shiprocket::assertRequested('GET', '/v1/external/orders/cancel'))->toThrow(AssertionFailedError::class)
        ->and(fn () => Shiprocket::assertNothingRequested())->toThrow(AssertionFailedError::class)
        ->and(fn () => Shiprocket::assertNotRequested('POST', '/v1/external/orders/cancel'))->toThrow(AssertionFailedError::class);

    Shiprocket::assertNotRequested('POST', '/v1/external/orders/create/adhoc');
});

it('matches the http method case-insensitively', function () {
    Shiprocket::fake();

    Shiprocket::account()->walletBalance();

    Shiprocket::assertRequested('get', '/v1/external/account/details/wallet-balance');
});

it('passes when nothing was requested', function () {
    Shiprocket::fake();

    Shiprocket::assertNothingRequested();
});

it('requires fake before assertions', function () {
    expect(fn () => Shiprocket::assertRequested('GET', '/v1/external/orders'))->toThrow(ShiprocketException::class, 'Shiprocket::fake() was not called.')
        ->and(fn () => Shiprocket::assertNotRequested('GET', '/v1/external/orders'))->toThrow(ShiprocketException::class)
        ->and(fn () => Shiprocket::assertNothingRequested())->toThrow(ShiprocketException::class);
});
