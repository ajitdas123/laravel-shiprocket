<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Services;

use Madgeek\LaravelShiprocket\Support\Delivery;
use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;

final class CourierService
{
    public function __construct(private readonly Delivery $delivery) {}

    /**
     * @param  'active'|'inactive'|'all'|null  $type
     */
    public function list(?string $type = null): ShiprocketResponse
    {
        return $this->delivery->send(
            'GET',
            '/v1/external/courier/courierListWithCounts',
            query: $type === null ? [] : ['type' => $type],
        );
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function serviceability(array $query): ShiprocketResponse
    {
        return $this->delivery->send('GET', '/v1/external/courier/serviceability/', query: $query);
    }

    /**
     * Pass `reassign: true` to change the courier of a shipment, which Shiprocket allows once every 24 hours.
     */
    public function assignAwb(int $shipmentId, ?int $courierId = null, bool $reassign = false): ShiprocketResponse
    {
        $payload = ['shipment_id' => $shipmentId];

        if ($courierId !== null) {
            $payload['courier_id'] = $courierId;
        }

        if ($reassign) {
            $payload['status'] = 'reassign';
        }

        return $this->delivery->send('POST', '/v1/external/courier/assign/awb', $payload);
    }

    /**
     * @param  int|list<int>  $shipmentIds
     * @param  array{pickup_date?: list<string>, status?: 'retry'}  $options
     */
    public function requestPickup(int|array $shipmentIds, array $options = []): ShiprocketResponse
    {
        return $this->delivery->send('POST', '/v1/external/courier/generate/pickup', [
            'shipment_id' => (array) $shipmentIds,
            ...$options,
        ]);
    }
}
