<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Exceptions;

final class InsufficientBalanceException extends ShiprocketException
{
    public function __construct(
        string $message,
        public readonly float $balance,
        public readonly float $required,
    ) {
        parent::__construct($message);
    }
}
