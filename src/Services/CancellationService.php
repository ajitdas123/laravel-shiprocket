<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Services;

use Madgeek\LaravelShiprocket\Exceptions\OrderHasShipmentException;
use Madgeek\LaravelShiprocket\Exceptions\ShipmentNotCancellableException;
use Madgeek\LaravelShiprocket\Support\CancellationResult;
use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;

final class CancellationService
{
    /**
     * Shiprocket allows shipment cancellation only before "Out for Pickup".
     * These statuses (and anything matching them) are blocked.
     *
     * @var list<string>
     */
    private const array BLOCKED_SHIPMENT_STATUSES = [
        'OUT FOR PICKUP',
        'PICKED UP',
        'IN TRANSIT',
        'SHIPPED',
        'OUT FOR DELIVERY',
        'DELIVERED',
        'RTO INITIATED',
        'RTO ACKNOWLEDGED',
        'RTO DELIVERED',
        'LOST',
        'DAMAGED',
    ];

    public function __construct(
        private readonly OrderService $orders,
        private readonly ShipmentService $shipments,
    ) {}

    /**
     * Cancel an order only when it has no assigned AWB/shipment.
     * If an AWB exists, use {@see orderAndShipment()} or {@see shipment()} first.
     */
    public function order(int $orderId): CancellationResult
    {
        $awb = $this->orderAwb($this->orders->find($orderId));

        if ($awb !== null) {
            throw new OrderHasShipmentException(
                "Order {$orderId} has shipment AWB {$awb} and cannot be canceled alone. Cancel the shipment first, or use cancel()->orderAndShipment().",
                $orderId,
                $awb,
            );
        }

        $this->orders->cancel($orderId);

        return new CancellationResult(
            orderId: $orderId,
            orderCanceled: true,
        );
    }

    /**
     * Cancel a shipment by AWB when it is not yet "Out for Pickup" (or later).
     */
    public function shipment(string $awb): CancellationResult
    {
        $status = $this->assertShipmentCancellable($awb);
        $this->shipments->cancel($awb);

        return new CancellationResult(
            awb: $awb,
            shipmentCanceled: true,
            shipmentStatus: $status,
        );
    }

    /**
     * Cancel the shipment (when an AWB exists) and then the order.
     */
    public function orderAndShipment(int $orderId): CancellationResult
    {
        $awb = $this->orderAwb($this->orders->find($orderId));
        $status = null;
        $shipmentCanceled = false;

        if ($awb !== null) {
            $status = $this->assertShipmentCancellable($awb);
            $this->shipments->cancel($awb);
            $shipmentCanceled = true;
        }

        $this->orders->cancel($orderId);

        return new CancellationResult(
            orderId: $orderId,
            awb: $awb,
            orderCanceled: true,
            shipmentCanceled: $shipmentCanceled,
            shipmentStatus: $status,
        );
    }

    private function orderAwb(ShiprocketResponse $order): ?string
    {
        $shipment = $order->json('data.shipments');

        if (! is_array($shipment)) {
            return null;
        }

        // Shiprocket usually returns one shipment object; sometimes a list.
        if (array_is_list($shipment)) {
            foreach ($shipment as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $awb = $this->stringOrNull($item['awb'] ?? $item['awb_code'] ?? null);

                if ($awb !== null) {
                    return $awb;
                }
            }

            return null;
        }

        return $this->stringOrNull($shipment['awb'] ?? $shipment['awb_code'] ?? null);
    }

    private function assertShipmentCancellable(string $awb): ?string
    {
        $status = $this->shipmentStatus($awb);

        if ($status !== null && $this->isBlockedShipmentStatus($status)) {
            throw new ShipmentNotCancellableException(
                "Shipment AWB {$awb} cannot be canceled because its status is [{$status}]. Shiprocket only allows cancellation before Out for Pickup.",
                $awb,
                $status,
            );
        }

        return $status;
    }

    private function shipmentStatus(string $awb): ?string
    {
        $track = $this->shipments->trackByAwb($awb);

        $current = $track->json('tracking_data.shipment_track.0.current_status');

        if (is_string($current) && $current !== '') {
            return $current;
        }

        $shipmentStatus = $track->json('tracking_data.shipment_status');

        if (is_string($shipmentStatus) && $shipmentStatus !== '') {
            return $shipmentStatus;
        }

        return null;
    }

    private function isBlockedShipmentStatus(string $status): bool
    {
        $normalized = strtoupper(trim($status));

        foreach (self::BLOCKED_SHIPMENT_STATUSES as $blocked) {
            if ($normalized === $blocked || str_contains($normalized, $blocked)) {
                return true;
            }
        }

        return false;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
