<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Testing;

use Illuminate\Support\Str;
use Madgeek\LaravelShiprocket\Exceptions\ShiprocketException;
use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;
use Madgeek\LaravelShiprocket\Transport\Transport;
use PHPUnit\Framework\Assert;

final class ShiprocketFake implements Transport
{
    /** @var list<RecordedRequest> */
    public array $requests = [];

    /**
     * @param  array<string, array<array-key, mixed>>  $responses  Response bodies keyed by endpoint pattern, e.g. "/v1/external/orders/*".
     */
    public function __construct(private readonly array $responses = []) {}

    public function request(string $method, string $endpoint, array $payload = [], array $query = []): ShiprocketResponse
    {
        $this->requests[] = new RecordedRequest($method, $endpoint, $payload, $query);

        foreach ($this->responses as $pattern => $body) {
            if (Str::is($pattern, $endpoint)) {
                return ShiprocketResponse::faked($body);
            }
        }

        return ShiprocketResponse::faked();
    }

    public function token(): string
    {
        return 'faked';
    }

    /**
     * @param  (callable(RecordedRequest): bool)|null  $callback
     */
    public function assertRequested(string $method, string $endpoint, ?callable $callback = null): void
    {
        Assert::assertNotEmpty(
            $this->matching($method, $endpoint, $callback),
            "An expected Shiprocket [{$method} {$endpoint}] request was not sent.",
        );
    }

    /**
     * @param  (callable(RecordedRequest): bool)|null  $callback
     */
    public function assertNotRequested(string $method, string $endpoint, ?callable $callback = null): void
    {
        Assert::assertEmpty(
            $this->matching($method, $endpoint, $callback),
            "An unexpected Shiprocket [{$method} {$endpoint}] request was sent.",
        );
    }

    public function assertNothingRequested(): void
    {
        Assert::assertEmpty($this->requests, 'Unexpected Shiprocket requests were sent.');
    }

    /**
     * @param  (callable(RecordedRequest): bool)|null  $callback
     * @return list<RecordedRequest>
     */
    private function matching(string $method, string $endpoint, ?callable $callback): array
    {
        return array_values(array_filter(
            $this->requests,
            static fn (RecordedRequest $request): bool => $request->method === strtoupper($method)
                && $request->endpoint === $endpoint
                && ($callback === null || $callback($request)),
        ));
    }

    public static function missing(): never
    {
        throw new ShiprocketException('Shiprocket::fake() was not called.');
    }
}
