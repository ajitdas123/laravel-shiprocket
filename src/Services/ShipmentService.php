<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Services;

use Madgeek\LaravelShiprocket\Support\Delivery;
use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;

final class ShipmentService
{
    public function __construct(private readonly Delivery $delivery) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): ShiprocketResponse
    {
        return $this->delivery->send('GET', '/v1/external/shipments', query: $filters);
    }

    public function find(int $shipmentId): ShiprocketResponse
    {
        return $this->delivery->send('GET', "/v1/external/shipments/{$shipmentId}");
    }

    public function trackByAwb(string $awb): ShiprocketResponse
    {
        return $this->delivery->send('GET', '/v1/external/courier/track/awb/'.rawurlencode($awb));
    }

    /**
     * @param  string|list<string>  $awbs
     */
    public function cancel(string|array $awbs): ShiprocketResponse
    {
        return $this->delivery->send('POST', '/v1/external/orders/cancel/shipment/awbs', [
            'awbs' => (array) $awbs,
        ]);
    }
}
