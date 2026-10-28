<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Services;

use Madgeek\LaravelShiprocket\Support\Delivery;
use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;

final class OrderService
{
    public function __construct(private readonly Delivery $delivery) {}

    /**
     * @param  array<string, mixed>  $order
     */
    public function create(array $order): ShiprocketResponse
    {
        return $this->delivery->send('POST', '/v1/external/orders/create/adhoc', $order);
    }

    /**
     * @param  array<string, mixed>  $order
     */
    public function createForChannel(array $order): ShiprocketResponse
    {
        return $this->delivery->send('POST', '/v1/external/orders/create', $order);
    }

    /**
     * @param  array<string, mixed>  $order
     */
    public function update(array $order): ShiprocketResponse
    {
        return $this->delivery->send('POST', '/v1/external/orders/update/adhoc', $order);
    }

    /**
     * @param  int|list<int>  $orderIds
     */
    public function updatePickupLocation(int|array $orderIds, string $pickupLocation): ShiprocketResponse
    {
        return $this->delivery->send('PATCH', '/v1/external/orders/address/pickup', [
            'order_id' => (array) $orderIds,
            'pickup_location' => $pickupLocation,
        ]);
    }

    /**
     * @param  array<string, mixed>  $address
     */
    public function updateDeliveryAddress(array $address): ShiprocketResponse
    {
        return $this->delivery->send('POST', '/v1/external/orders/address/update', $address);
    }

    /**
     * @param  int|list<int>  $orderIds
     */
    public function cancel(int|array $orderIds): ShiprocketResponse
    {
        return $this->delivery->send('POST', '/v1/external/orders/cancel', [
            'ids' => (array) $orderIds,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): ShiprocketResponse
    {
        return $this->delivery->send('GET', '/v1/external/orders', query: $filters);
    }

    public function find(int $orderId): ShiprocketResponse
    {
        return $this->delivery->send('GET', "/v1/external/orders/show/{$orderId}");
    }

    public function export(): ShiprocketResponse
    {
        return $this->delivery->send('POST', '/v1/external/orders/export');
    }
}
