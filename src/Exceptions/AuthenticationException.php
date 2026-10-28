<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Exceptions;

use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;

final class AuthenticationException extends ShiprocketException
{
    public function __construct(
        string $message = 'Shiprocket authentication failed.',
        public readonly ?ShiprocketResponse $response = null,
    ) {
        parent::__construct($message);
    }
}
