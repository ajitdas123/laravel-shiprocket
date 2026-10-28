<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Exceptions;

use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;

final class RequestFailedException extends ShiprocketException
{
    public function __construct(string $message, public readonly ShiprocketResponse $response)
    {
        parent::__construct($message);
    }
}
