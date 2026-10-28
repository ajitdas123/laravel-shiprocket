<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Madgeek\LaravelShiprocket\Facades\Shiprocket;

it('prints the wallet balance', function () {
    Shiprocket::fake([
        '/v1/external/account/details/wallet-balance' => ['data' => ['balance_amount' => '-291539.83']],
    ]);

    $this->artisan('shiprocket:wallet')
        ->expectsOutputToContain('Shiprocket wallet balance: -291539.83')
        ->assertSuccessful();

    Shiprocket::assertRequested('GET', '/v1/external/account/details/wallet-balance');
});

it('reports an accepted request without a balance through the log driver', function () {
    $this->artisan('shiprocket:wallet')
        ->expectsOutputToContain('Shiprocket request accepted: logged.')
        ->assertSuccessful();
});

it('fails when shiprocket rejects the request', function () {
    Http::fake([
        '*' => Http::response(['message' => 'Invalid email and password combination'], 400),
    ]);
    useHttpDriver();

    $this->artisan('shiprocket:wallet')
        ->expectsOutputToContain('Invalid email and password combination')
        ->assertFailed();
});
