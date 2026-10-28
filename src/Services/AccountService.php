<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Services;

use Madgeek\LaravelShiprocket\Support\Delivery;
use Madgeek\LaravelShiprocket\Support\ShiprocketResponse;

final class AccountService
{
    public function __construct(private readonly Delivery $delivery) {}

    public function walletBalance(): ShiprocketResponse
    {
        return $this->delivery->send('GET', '/v1/external/account/details/wallet-balance');
    }
}
