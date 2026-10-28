<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Madgeek\LaravelShiprocket\Exceptions\ShiprocketException;
use Madgeek\LaravelShiprocket\Facades\Shiprocket;
use Madgeek\LaravelShiprocket\ShiprocketManager;
use Madgeek\LaravelShiprocket\ShiprocketServiceProvider;
use Madgeek\LaravelShiprocket\Transport\HttpTransport;
use Madgeek\LaravelShiprocket\Transport\LogTransport;
use Madgeek\LaravelShiprocket\Transport\Transport;

it('merges package config and lets the app override it', function () {
    expect(config('shiprocket.base_url'))->toBe('https://apiv2.shiprocket.in')
        ->and(config('shiprocket.token_cache_key'))->toBe('shiprocket.token')
        ->and(config('shiprocket.token_ttl'))->toBe(777600);

    config(['shiprocket.base_url' => 'https://sandbox.example.test']);

    expect(config('shiprocket.base_url'))->toBe('https://sandbox.example.test');
});

it('publishes the shiprocket config file', function () {
    expect(ServiceProvider::pathsToPublish(ShiprocketServiceProvider::class, 'laravel-shiprocket-config'))
        ->toContain(config_path('shiprocket.php'))
        ->and(ServiceProvider::pathsToPublish(ShiprocketServiceProvider::class, 'laravel-shiprocket'))
        ->toContain(config_path('shiprocket.php'));
});

it('resolves the manager as a singleton behind the facade', function () {
    expect(app(ShiprocketManager::class))->toBe(app(ShiprocketManager::class))
        ->and(Shiprocket::getFacadeRoot())->toBe(app(ShiprocketManager::class));
});

it('binds the log and http transports from the driver', function () {
    expect(app(Transport::class))->toBeInstanceOf(LogTransport::class);

    config(['shiprocket.driver' => 'http']);
    resetShiprocket();

    expect(app(Transport::class))->toBeInstanceOf(HttpTransport::class);
});

it('rejects an unknown driver', function () {
    config(['shiprocket.driver' => 'pigeon']);
    resetShiprocket();

    expect(fn () => app(Transport::class))->toThrow(ShiprocketException::class, 'Unsupported Shiprocket driver [pigeon].');
});

it('rejects a driver that is not a string', function () {
    config(['shiprocket.driver' => ['http']]);
    resetShiprocket();

    expect(fn () => app(Transport::class))->toThrow(ShiprocketException::class, 'Shiprocket driver must be a string.');
});

it('registers the wallet command', function () {
    expect(array_keys(Artisan::all()))->toContain('shiprocket:wallet');
});
