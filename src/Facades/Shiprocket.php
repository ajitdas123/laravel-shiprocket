<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Facades;

use Illuminate\Support\Facades\Facade;
use Madgeek\LaravelShiprocket\Credentials\Credentials;
use Madgeek\LaravelShiprocket\Services\AccountService;
use Madgeek\LaravelShiprocket\Services\AuthService;
use Madgeek\LaravelShiprocket\Services\CourierService;
use Madgeek\LaravelShiprocket\Services\DocumentService;
use Madgeek\LaravelShiprocket\Services\OrderService;
use Madgeek\LaravelShiprocket\Services\PickupLocationService;
use Madgeek\LaravelShiprocket\Services\ShipmentService;
use Madgeek\LaravelShiprocket\ShiprocketManager;
use Madgeek\LaravelShiprocket\Support\Delivery;
use Madgeek\LaravelShiprocket\Testing\RecordedRequest;
use Madgeek\LaravelShiprocket\Testing\ShiprocketFake;
use Madgeek\LaravelShiprocket\Transport\HttpTransport;
use Madgeek\LaravelShiprocket\Transport\LogTransport;
use Madgeek\LaravelShiprocket\Transport\Transport;

/**
 * @method static AuthService auth()
 * @method static OrderService orders()
 * @method static CourierService couriers()
 * @method static ShipmentService shipments()
 * @method static DocumentService documents()
 * @method static PickupLocationService pickupLocations()
 * @method static AccountService account()
 *
 * Authentication is automatic from SHIPROCKET_EMAIL and SHIPROCKET_PASSWORD.
 * Prefer orders(), couriers(), and the other services over auth() unless you
 * explicitly need getToken(), login(), or logout().
 *
 * @see ShiprocketManager
 */
final class Shiprocket extends Facade
{
    /**
     * @param  array<string, array<array-key, mixed>>  $responses  Response bodies keyed by endpoint pattern, e.g. "/v1/external/orders/*".
     */
    public static function fake(array $responses = []): ShiprocketFake
    {
        $fake = new ShiprocketFake($responses);

        self::swapTransport($fake);

        return $fake;
    }

    /**
     * @param  (callable(RecordedRequest): bool)|null  $callback
     */
    public static function assertRequested(string $method, string $endpoint, ?callable $callback = null): void
    {
        self::fakeTransport()->assertRequested($method, $endpoint, $callback);
    }

    /**
     * @param  (callable(RecordedRequest): bool)|null  $callback
     */
    public static function assertNotRequested(string $method, string $endpoint, ?callable $callback = null): void
    {
        self::fakeTransport()->assertNotRequested($method, $endpoint, $callback);
    }

    public static function assertNothingRequested(): void
    {
        self::fakeTransport()->assertNothingRequested();
    }

    protected static function getFacadeAccessor(): string
    {
        return ShiprocketManager::class;
    }

    private static function fakeTransport(): ShiprocketFake
    {
        /** @var mixed $transport */
        $transport = self::getFacadeApplication()->make(Transport::class);

        if (! $transport instanceof ShiprocketFake) {
            ShiprocketFake::missing();
        }

        return $transport;
    }

    private static function swapTransport(ShiprocketFake $fake): void
    {
        $app = self::getFacadeApplication();

        foreach ([
            HttpTransport::class,
            LogTransport::class,
            Delivery::class,
            AuthService::class,
            OrderService::class,
            CourierService::class,
            ShipmentService::class,
            DocumentService::class,
            PickupLocationService::class,
            AccountService::class,
            ShiprocketManager::class,
            Credentials::class,
        ] as $abstract) {
            $app->forgetInstance($abstract);
        }

        $app->forgetInstance(Transport::class);
        $app->instance(Transport::class, $fake);

        self::clearResolvedInstance(self::getFacadeAccessor());
    }
}
