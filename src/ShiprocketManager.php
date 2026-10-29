<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket;

use Madgeek\LaravelShiprocket\Services\AccountService;
use Madgeek\LaravelShiprocket\Services\AuthService;
use Madgeek\LaravelShiprocket\Services\CourierService;
use Madgeek\LaravelShiprocket\Services\DocumentService;
use Madgeek\LaravelShiprocket\Services\FulfillmentService;
use Madgeek\LaravelShiprocket\Services\OrderService;
use Madgeek\LaravelShiprocket\Services\PickupLocationService;
use Madgeek\LaravelShiprocket\Services\ShipmentService;

final class ShiprocketManager
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly OrderService $orders,
        private readonly CourierService $couriers,
        private readonly ShipmentService $shipments,
        private readonly DocumentService $documents,
        private readonly PickupLocationService $pickupLocations,
        private readonly AccountService $account,
        private readonly FulfillmentService $fulfill,
    ) {}

    public function auth(): AuthService
    {
        return $this->auth;
    }

    public function orders(): OrderService
    {
        return $this->orders;
    }

    public function couriers(): CourierService
    {
        return $this->couriers;
    }

    public function shipments(): ShipmentService
    {
        return $this->shipments;
    }

    public function documents(): DocumentService
    {
        return $this->documents;
    }

    public function pickupLocations(): PickupLocationService
    {
        return $this->pickupLocations;
    }

    public function account(): AccountService
    {
        return $this->account;
    }

    public function fulfill(): FulfillmentService
    {
        return $this->fulfill;
    }
}
