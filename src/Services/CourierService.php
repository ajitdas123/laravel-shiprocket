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
     * Call Shiprocket's serviceability endpoint with the raw query parameters.
     *
     * Prefer {@see isServiceable()}, {@see availableCouriers()}, or {@see isCodAvailable()}
     * when you only need a boolean check or the courier list.
     *
     * @param  array<string, mixed>  $query
     */
    public function serviceability(array $query): ShiprocketResponse
    {
        return $this->delivery->send('GET', '/v1/external/courier/serviceability/', query: $query);
    }

    /**
     * Check whether any courier can ship from `$from` to `$to`.
     *
     * Returns a ShiprocketResponse with `serviceable` set and an empty `data` payload.
     * COD defaults to prepaid (`false`). Weight defaults to `0.5` kg because Shiprocket
     * requires it when no order id is provided.
     */
    public function isServiceable(
        int|string $from,
        int|string $to,
        bool $cod = false,
        float|int|string $weight = 0.5,
    ): ShiprocketResponse {
        $response = $this->serviceability($this->serviceabilityQuery($from, $to, $cod, $weight));

        return $this->flagResponse($response, 'serviceable', $this->availableCourierCompanies($response) !== []);
    }

    /**
     * Return available couriers (with rates and related fields) for the route.
     *
     * COD defaults to prepaid (`false`). Weight defaults to `0.5` kg.
     */
    public function availableCouriers(
        int|string $from,
        int|string $to,
        bool $cod = false,
        float|int|string $weight = 0.5,
    ): ShiprocketResponse {
        $response = $this->serviceability($this->serviceabilityQuery($from, $to, $cod, $weight));

        return new ShiprocketResponse(
            successful: $response->successful,
            status: $response->status,
            body: [
                'data' => [
                    'available_courier_companies' => $this->availableCourierCompanies($response),
                ],
            ],
            message: $response->message,
        );
    }

    /**
     * Check whether COD is available from `$from` to `$to`.
     *
     * Returns a ShiprocketResponse with `cod_available` set and an empty `data` payload.
     * Weight defaults to `0.5` kg.
     */
    public function isCodAvailable(
        int|string $from,
        int|string $to,
        float|int|string $weight = 0.5,
    ): ShiprocketResponse {
        $response = $this->serviceability($this->serviceabilityQuery($from, $to, true, $weight));

        return $this->flagResponse($response, 'cod_available', $this->hasCodCourier($response));
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

    /**
     * @return array{pickup_postcode: int|string, delivery_postcode: int|string, cod: int, weight: float|int|string}
     */
    private function serviceabilityQuery(
        int|string $from,
        int|string $to,
        bool $cod,
        float|int|string $weight,
    ): array {
        return [
            'pickup_postcode' => $from,
            'delivery_postcode' => $to,
            'cod' => $cod ? 1 : 0,
            'weight' => $weight,
        ];
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function availableCourierCompanies(ShiprocketResponse $response): array
    {
        $couriers = $response->json('data.available_courier_companies', []);

        if (! is_array($couriers)) {
            return [];
        }

        $normalized = [];

        foreach ($couriers as $courier) {
            if (is_array($courier)) {
                $normalized[] = $courier;
            }
        }

        return $normalized;
    }

    private function hasCodCourier(ShiprocketResponse $response): bool
    {
        foreach ($this->availableCourierCompanies($response) as $courier) {
            if ((int) ($courier['cod'] ?? 0) === 1) {
                return true;
            }
        }

        return false;
    }

    private function flagResponse(ShiprocketResponse $response, string $flag, bool $value): ShiprocketResponse
    {
        return new ShiprocketResponse(
            successful: $response->successful,
            status: $response->status,
            body: [
                $flag => $value,
                'data' => [],
            ],
            message: $response->message,
        );
    }
}
