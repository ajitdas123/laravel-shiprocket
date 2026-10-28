<?php

declare(strict_types=1);

namespace Madgeek\LaravelShiprocket\Commands;

use Illuminate\Console\Command;
use Madgeek\LaravelShiprocket\Exceptions\ShiprocketException;
use Madgeek\LaravelShiprocket\ShiprocketManager;

final class CheckWalletCommand extends Command
{
    protected $signature = 'shiprocket:wallet';

    protected $description = 'Check the Shiprocket connection by fetching the wallet balance';

    public function handle(ShiprocketManager $shiprocket): int
    {
        try {
            $response = $shiprocket->account()->walletBalance();
        } catch (ShiprocketException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $balance = $response->json('data.balance_amount');

        if (! is_string($balance) && ! is_int($balance) && ! is_float($balance)) {
            $this->components->info('Shiprocket request accepted: '.($response->message ?? 'no balance returned').'.');

            return self::SUCCESS;
        }

        $this->components->info("Shiprocket wallet balance: {$balance}");

        return self::SUCCESS;
    }
}
