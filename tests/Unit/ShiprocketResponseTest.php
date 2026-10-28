<?php

declare(strict_types=1);

use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;

it('normalizes http payloads', function () {
    $success = ShiprocketResponse::fromHttp(200, [
        'message' => 'Pickup location Updated',
        'data' => ['balance_amount' => '12.50'],
    ]);

    $accepted = ShiprocketResponse::fromHttp(202, ['status' => 1]);

    $failed = ShiprocketResponse::fromHttp(422, ['message' => ['not a string']]);

    expect($success->successful)->toBeTrue()
        ->and($success->message)->toBe('Pickup location Updated')
        ->and($success->json('data.balance_amount'))->toBe('12.50')
        ->and($success->json('missing', 'default'))->toBe('default')
        ->and($success->json())->toBe($success->body)
        ->and($accepted->successful)->toBeTrue()
        ->and($accepted->message)->toBeNull()
        ->and($failed->successful)->toBeFalse()
        ->and($failed->message)->toBeNull();
});

it('builds logged and faked responses', function () {
    expect(ShiprocketResponse::logged()->message)->toBe('logged')
        ->and(ShiprocketResponse::faked()->message)->toBe('faked')
        ->and(ShiprocketResponse::faked(['status' => 1])->json('status'))->toBe(1);
});
