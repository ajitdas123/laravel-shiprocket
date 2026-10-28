<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Testing;

final readonly class RecordedRequest
{
    /**
     * @param  array<array-key, mixed>  $payload
     * @param  array<string, mixed>  $query
     */
    public function __construct(
        public string $method,
        public string $endpoint,
        public array $payload,
        public array $query,
    ) {}
}
