<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Exceptions;

final class OrderHasShipmentException extends ShiprocketException
{
    public function __construct(
        string $message,
        public readonly mixed $orderId,
        public readonly string $awb,
    ) {
        parent::__construct($message);
    }
}
