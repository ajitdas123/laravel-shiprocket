<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Exceptions;

final class ShipmentNotCancellableException extends ShiprocketException
{
    public function __construct(
        string $message,
        public readonly string $awb,
        public readonly ?string $status,
    ) {
        parent::__construct($message);
    }
}
