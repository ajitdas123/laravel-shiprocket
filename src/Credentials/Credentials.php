<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Credentials;

final readonly class Credentials
{
    public function __construct(
        public string $email,
        public string $password,
        public string $baseUrl,
        public string $tokenCacheKey,
        public int $tokenTtl,
    ) {}

    /**
     * @param  array<mixed, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            email: self::stringValue($config['email'] ?? null),
            password: self::stringValue($config['password'] ?? null),
            baseUrl: rtrim(self::stringValue($config['base_url'] ?? null, 'https://apiv2.shiprocket.in'), '/'),
            tokenCacheKey: self::stringValue($config['token_cache_key'] ?? null, 'shiprocket.token'),
            tokenTtl: self::intValue($config['token_ttl'] ?? null, 777600),
        );
    }

    public function hasLogin(): bool
    {
        return $this->email !== '' && $this->password !== '';
    }

    public function url(string $endpoint): string
    {
        return $this->baseUrl.'/'.ltrim($endpoint, '/');
    }

    private static function stringValue(mixed $value, string $default = ''): string
    {
        if (! is_string($value) || $value === '') {
            return $default;
        }

        return $value;
    }

    private static function intValue(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
