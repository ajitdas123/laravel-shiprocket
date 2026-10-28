<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Support;

use Illuminate\Support\Arr;

final readonly class ShiprocketResponse
{
    /**
     * @param  array<array-key, mixed>  $body
     */
    public function __construct(
        public bool $successful,
        public int $status,
        public array $body,
        public ?string $message,
    ) {}

    /**
     * @param  array<array-key, mixed>  $body
     */
    public static function fromHttp(int $status, array $body): self
    {
        $message = isset($body['message']) && is_string($body['message']) ? $body['message'] : null;

        return new self(
            successful: $status >= 200 && $status < 300,
            status: $status,
            body: $body,
            message: $message,
        );
    }

    public static function logged(): self
    {
        return new self(true, 200, ['message' => 'logged'], 'logged');
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    public static function faked(array $body = []): self
    {
        return self::fromHttp(200, $body === [] ? ['message' => 'faked'] : $body);
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }

        return Arr::get($this->body, $key, $default);
    }
}
