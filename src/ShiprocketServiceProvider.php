<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use Madgeek\LaravelShiprocket\Commands\CheckWalletCommand;
use Madgeek\LaravelShiprocket\Credentials\Credentials;
use Madgeek\LaravelShiprocket\Exceptions\ShiprocketException;
use Madgeek\LaravelShiprocket\Services\AccountService;
use Madgeek\LaravelShiprocket\Services\AuthService;
use Madgeek\LaravelShiprocket\Services\CourierService;
use Madgeek\LaravelShiprocket\Services\DocumentService;
use Madgeek\LaravelShiprocket\Services\OrderService;
use Madgeek\LaravelShiprocket\Services\PickupLocationService;
use Madgeek\LaravelShiprocket\Services\ShipmentService;
use Madgeek\LaravelShiprocket\Support\Delivery;
use Madgeek\LaravelShiprocket\Transport\HttpTransport;
use Madgeek\LaravelShiprocket\Transport\LogTransport;
use Madgeek\LaravelShiprocket\Transport\Transport;
use Psr\Log\LoggerInterface;

class ShiprocketServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shiprocket.php', 'shiprocket');

        $this->app->singleton(Credentials::class, function (): Credentials {
            return Credentials::fromConfig($this->configArray('shiprocket'));
        });

        $this->app->singleton(HttpTransport::class, function (): HttpTransport {
            return new HttpTransport(
                $this->app->make(Factory::class),
                $this->app->make(Cache::class),
                $this->app->make(Credentials::class),
                $this->configInt('shiprocket.http.timeout', 30),
                $this->configInt('shiprocket.http.retry', 0),
            );
        });

        $this->app->singleton(LogTransport::class, function (): LogTransport {
            return new LogTransport($this->app->make(LoggerInterface::class));
        });

        $this->app->singleton(Transport::class, function (): Transport {
            $driver = $this->app->make(Repository::class)->get('shiprocket.driver', 'http');

            if (! is_string($driver)) {
                throw new ShiprocketException('Shiprocket driver must be a string.');
            }

            return match ($driver) {
                'log' => $this->app->make(LogTransport::class),
                'http' => $this->app->make(HttpTransport::class),
                default => throw new ShiprocketException("Unsupported Shiprocket driver [{$driver}]."),
            };
        });

        $this->app->singleton(Delivery::class);
        $this->app->singleton(AuthService::class);
        $this->app->singleton(OrderService::class);
        $this->app->singleton(CourierService::class);
        $this->app->singleton(ShipmentService::class);
        $this->app->singleton(DocumentService::class);
        $this->app->singleton(PickupLocationService::class);
        $this->app->singleton(AccountService::class);
        $this->app->singleton(ShiprocketManager::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/shiprocket.php' => config_path('shiprocket.php'),
        ], ['laravel-shiprocket', 'laravel-shiprocket-config']);

        $this->commands([
            CheckWalletCommand::class,
        ]);
    }

    /**
     * @return array<mixed, mixed>
     */
    private function configArray(string $key): array
    {
        $value = $this->app->make(Repository::class)->get($key, []);

        if (! is_array($value)) {
            return [];
        }

        return $value;
    }

    private function configInt(string $key, int $default): int
    {
        $value = $this->app->make(Repository::class)->get($key, $default);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
