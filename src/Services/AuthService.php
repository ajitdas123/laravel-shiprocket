<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Services;

use Madgeek\LaravelShiprocket\Support\Delivery;
use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;

final class AuthService
{
    public const string LOGIN_ENDPOINT = '/v1/external/auth/login';

    public const string LOGOUT_ENDPOINT = '/v1/external/auth/logout';

    public function __construct(private readonly Delivery $delivery) {}

    /**
     * Force a login with the configured email and password, cache the JWT, and return the full response.
     *
     * Most apps never need this. Other package methods authenticate automatically.
     */
    public function login(): ShiprocketResponse
    {
        return $this->delivery->send('POST', self::LOGIN_ENDPOINT);
    }

    /**
     * Return a usable Bearer token from cache, or log in and cache a new one.
     *
     * Most apps never need this. Other package methods attach the token automatically.
     */
    public function getToken(): string
    {
        return $this->delivery->transport()->token();
    }

    /**
     * Invalidate the current token with Shiprocket and clear the local cache.
     */
    public function logout(): ShiprocketResponse
    {
        return $this->delivery->send('POST', self::LOGOUT_ENDPOINT);
    }
}
