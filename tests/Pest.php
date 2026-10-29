<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Tests {
    use ArrayObject;
    use Illuminate\Log\Events\MessageLogged;
    use Illuminate\Support\Facades\Log;
    use Madgeek\LaravelShiprocket\Credentials\Credentials;
    use Madgeek\LaravelShiprocket\Facades\Shiprocket;
    use Madgeek\LaravelShiprocket\Services\AccountService;
    use Madgeek\LaravelShiprocket\Services\AuthService;
    use Madgeek\LaravelShiprocket\Services\CourierService;
    use Madgeek\LaravelShiprocket\Services\DocumentService;
    use Madgeek\LaravelShiprocket\Services\FulfillmentService;
    use Madgeek\LaravelShiprocket\Services\OrderService;
    use Madgeek\LaravelShiprocket\Services\PickupLocationService;
    use Madgeek\LaravelShiprocket\Services\ShipmentService;
    use Madgeek\LaravelShiprocket\ShiprocketManager;
    use Madgeek\LaravelShiprocket\Support\Delivery;
    use Madgeek\LaravelShiprocket\Transport\HttpTransport;
    use Madgeek\LaravelShiprocket\Transport\LogTransport;
    use Madgeek\LaravelShiprocket\Transport\Transport;
    use ReflectionMethod;

    function resetShiprocket(): void
    {
        foreach ([
            Transport::class,
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
            FulfillmentService::class,
            ShiprocketManager::class,
            Credentials::class,
        ] as $abstract) {
            app()->forgetInstance($abstract);
        }

        $reflection = new ReflectionMethod(Shiprocket::class, 'clearResolvedInstance');
        $reflection->invoke(null, ShiprocketManager::class);
    }

    /**
     * @return ArrayObject<int, MessageLogged>
     */
    function captureLogs(): ArrayObject
    {
        $logged = new ArrayObject;

        Log::listen(function (MessageLogged $event) use ($logged): void {
            if ($event->message === 'Shiprocket request logged.') {
                $logged->append($event);
            }
        });

        return $logged;
    }
}

namespace {
    use Madgeek\LaravelShiprocket\Tests\TestCase;

    uses(TestCase::class)->in(__DIR__);

    function resetShiprocket(): void
    {
        Madgeek\LaravelShiprocket\Tests\resetShiprocket();
    }

    function captureLogs(): ArrayObject
    {
        return Madgeek\LaravelShiprocket\Tests\captureLogs();
    }

    function useHttpDriver(array $config = []): void
    {
        config(['shiprocket.driver' => 'http', ...$config]);
        resetShiprocket();
    }
}
