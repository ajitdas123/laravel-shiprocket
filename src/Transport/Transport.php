<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Transport;

use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;

interface Transport
{
    /**
     * @param  array<array-key, mixed>  $payload
     * @param  array<string, mixed>  $query
     */
    public function request(string $method, string $endpoint, array $payload = [], array $query = []): ShiprocketResponse;

    /**
     * Return a usable Bearer token, logging in and caching when needed.
     */
    public function token(): string;
}
