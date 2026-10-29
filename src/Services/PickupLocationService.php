<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Services;

use Madgeek\LaravelShiprocket\Support\Delivery;
use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;

final class PickupLocationService
{
    public function __construct(private readonly Delivery $delivery) {}

    /**
     * List every pickup location on the Shiprocket account.
     */
    public function list(): ShiprocketResponse
    {
        return $this->delivery->send('GET', '/v1/external/settings/company/pickup');
    }

    /**
     * Add a pickup location. Required keys: pickup_location, name, email, phone,
     * address, city, state, country, pin_code. Optional: address_2, lat, long,
     * address_type, vendor_name, gstin.
     *
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): ShiprocketResponse
    {
        return $this->delivery->send('POST', '/v1/external/settings/company/addpickup', $payload);
    }
}
