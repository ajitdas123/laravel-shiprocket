<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Support;

use Illuminate\Contracts\Events\Dispatcher;
use Madgeek\LaravelShiprocket\Events\RequestFailed;
use Madgeek\LaravelShiprocket\Events\RequestSending;
use Madgeek\LaravelShiprocket\Events\RequestSent;
use Madgeek\LaravelShiprocket\Exceptions\ShiprocketException;
use Madgeek\LaravelShiprocket\Transport\Transport;

final class Delivery
{
    public function __construct(
        private readonly Transport $transport,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     * @param  array<string, mixed>  $query
     */
    public function send(string $method, string $endpoint, array $payload = [], array $query = []): ShiprocketResponse
    {
        $this->events->dispatch(new RequestSending($method, $endpoint, $payload, $query));

        try {
            $response = $this->transport->request($method, $endpoint, $payload, $query);
        } catch (ShiprocketException $exception) {
            $this->events->dispatch(new RequestFailed($method, $endpoint, $payload, $query, $exception));

            throw $exception;
        }

        $this->events->dispatch(new RequestSent($method, $endpoint, $payload, $query, $response));

        return $response;
    }

    public function transport(): Transport
    {
        return $this->transport;
    }
}
