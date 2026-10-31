<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Support;

final readonly class CancellationResult
{
    public function __construct(
        public mixed $orderId = null,
        public ?string $awb = null,
        public bool $orderCanceled = false,
        public bool $shipmentCanceled = false,
        public ?string $shipmentStatus = null,
    ) {}

    /**
     * @return array{
     *     order_id: mixed,
     *     awb: ?string,
     *     order_canceled: bool,
     *     shipment_canceled: bool,
     *     shipment_status: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'awb' => $this->awb,
            'order_canceled' => $this->orderCanceled,
            'shipment_canceled' => $this->shipmentCanceled,
            'shipment_status' => $this->shipmentStatus,
        ];
    }
}
