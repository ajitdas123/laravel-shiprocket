<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Transport;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Madgeek\LaravelShiprocket\Credentials\Credentials;
use Madgeek\LaravelShiprocket\Exceptions\AuthenticationException;
use Madgeek\LaravelShiprocket\Exceptions\RequestFailedException;
use Madgeek\LaravelShiprocket\Services\AuthService;
use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;
use Throwable;

final class HttpTransport implements Transport
{
    public function __construct(
        private readonly Factory $http,
        private readonly Cache $cache,
        private readonly Credentials $credentials,
        private readonly int $timeout,
        private readonly int $retry,
    ) {}

    public function request(string $method, string $endpoint, array $payload = [], array $query = []): ShiprocketResponse
    {
        if ($endpoint === AuthService::LOGIN_ENDPOINT) {
            return $this->login();
        }

        $response = $this->send($method, $endpoint, $payload, $query, $this->token());

        if ($response->status === 401) {
            $this->cache->forget($this->credentials->tokenCacheKey);

            $response = $this->send($method, $endpoint, $payload, $query, $this->token());
        }

        if ($response->status === 401) {
            throw new AuthenticationException($response->message ?? 'Shiprocket authentication failed.', $response);
        }

        if (! $response->successful) {
            throw new RequestFailedException($response->message ?? 'Shiprocket request failed.', $response);
        }

        if ($endpoint === AuthService::LOGOUT_ENDPOINT) {
            $this->cache->forget($this->credentials->tokenCacheKey);
        }

        return $response;
    }

    public function token(): string
    {
        $cached = $this->cache->get($this->credentials->tokenCacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->login()->json('token');

        return is_string($token) ? $token : '';
    }

    private function login(): ShiprocketResponse
    {
        if (! $this->credentials->hasLogin()) {
            throw new AuthenticationException('Shiprocket email and password are not configured.');
        }

        $response = $this->send('POST', AuthService::LOGIN_ENDPOINT, [
            'email' => $this->credentials->email,
            'password' => $this->credentials->password,
        ]);

        if (! $response->successful) {
            throw new AuthenticationException($response->message ?? 'Shiprocket authentication failed.', $response);
        }

        $token = $response->json('token');

        if (! is_string($token) || $token === '') {
            throw new AuthenticationException('Shiprocket did not return an auth token.', $response);
        }

        $this->cache->put($this->credentials->tokenCacheKey, $token, $this->credentials->tokenTtl);

        return $response;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @param  array<string, mixed>  $query
     */
    private function send(string $method, string $endpoint, array $payload = [], array $query = [], ?string $token = null): ShiprocketResponse
    {
        $pending = $this->http
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);

        if ($token !== null) {
            $pending = $pending->withToken($token);
        }

        if ($query !== []) {
            $pending = $pending->withQueryParameters($query);
        }

        if ($this->retry > 0) {
            $pending = $pending->retry(
                $this->retry,
                100,
                static fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                throw: false,
            );
        }

        try {
            $response = $pending->send($method, $this->credentials->url($endpoint), $payload === [] ? [] : ['json' => $payload]);
        } catch (ConnectionException $exception) {
            throw new RequestFailedException(
                $exception->getMessage(),
                new ShiprocketResponse(false, 0, ['message' => $exception->getMessage()], $exception->getMessage()),
            );
        }

        $decoded = $response->json();

        return ShiprocketResponse::fromHttp(
            $response->status(),
            is_array($decoded) ? $decoded : ['message' => $response->body()],
        );
    }
}
