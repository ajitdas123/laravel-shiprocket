<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Exceptions;

use Madgeek\LaravelShiprocket\Support\FulfillmentResult;
use Throwable;

final class ShipmentPipelineException extends ShiprocketException
{
    public function __construct(
        string $message,
        public readonly string $step,
        public readonly FulfillmentResult $result,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function result(): FulfillmentResult
    {
        return $this->result;
    }

    public function step(): string
    {
        return $this->step;
    }
}
