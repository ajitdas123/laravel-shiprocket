<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Tests;

use Illuminate\Foundation\Application;
use Madgeek\LaravelShiprocket\ShiprocketServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ShiprocketServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('shiprocket.driver', 'log');
        $app['config']->set('shiprocket.email', 'api@example.com');
        $app['config']->set('shiprocket.password', 'secret');
        $app['config']->set('shiprocket.http', [
            'timeout' => 10,
            'retry' => 0,
        ]);
    }
}
