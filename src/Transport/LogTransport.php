<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Transport;

use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;
use Psr\Log\LoggerInterface;

final class LogTransport implements Transport
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function request(string $method, string $endpoint, array $payload = [], array $query = []): ShiprocketResponse
    {
        $this->logger->info('Shiprocket request logged.', [
            'method' => $method,
            'endpoint' => $endpoint,
            'payload' => $payload,
            'query' => $query,
        ]);

        return ShiprocketResponse::logged();
    }

    public function token(): string
    {
        return 'logged';
    }
}
