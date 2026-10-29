<?php

declare(strict_types=1);

use Madgeek\LaravelShiprocket\Exceptions\InsufficientBalanceException;
use Madgeek\LaravelShiprocket\Exceptions\ShipmentPipelineException;
use Madgeek\LaravelShiprocket\Facades\Shiprocket;
use Madgeek\LaravelShiprocket\Testing\RecordedRequest;

function fulfillmentOrder(array $overrides = []): array
{
    return [
        'order_id' => '224-447',
        'order_date' => '2019-07-24 11:11',
        'pickup_location' => 'Jammu',
        'billing_customer_name' => 'Naruto',
        'billing_last_name' => 'Uzumaki',
        'billing_address' => 'House 221B, Leaf Village',
        'billing_city' => 'New Delhi',
        'billing_pincode' => 110002,
        'billing_state' => 'Delhi',
        'billing_country' => 'India',
        'billing_email' => 'naruto@uzumaki.com',
        'billing_phone' => 9876543210,
        'shipping_is_billing' => true,
        'payment_method' => 'Prepaid',
        'sub_total' => 100,
        'weight' => 0.5,
        'order_items' => [
            ['name' => 'Kunai', 'sku' => 'chakra1', 'units' => 1, 'selling_price' => 100],
        ],
        ...$overrides,
    ];
}

function forwardPayload(array $overrides = []): array
{
    return [
        'pickup_location_added' => 0,
        'order_created' => 1,
        'awb_generated' => 1,
        'label_generated' => 1,
        'pickup_generated' => 1,
        'manifest_generated' => 1,
        'pickup_scheduled_date' => '2022-06-04 09:00:00',
        'order_id' => 222521420,
        'shipment_id' => 222002884,
        'awb_code' => '14326421307048',
        'courier_company_id' => 25,
        'courier_name' => 'Xpressbees 5kg',
        'label_url' => 'https://example.test/label.pdf',
        'manifest_url' => 'https://example.test/manifest.pdf',
        'pickup_token_number' => 'TOKEN-1',
        ...$overrides,
    ];
}

it('creates a shipment with label manifest and invoice after checking wallet balance', function () {
    Shiprocket::fake([
        '/v1/external/account/details/wallet-balance' => [
            'data' => ['balance_amount' => '500.00'],
        ],
        '/v1/external/shipments/create/forward-shipment' => [
            'status' => 1,
            'payload' => forwardPayload(),
        ],
        '/v1/external/orders/print/invoice' => [
            'is_invoice_created' => true,
            'invoice_url' => 'https://example.test/invoice.pdf',
            'not_created' => [],
        ],
    ]);

    $result = Shiprocket::fulfill()->create(fulfillmentOrder(), [
        'courier_id' => 25,
        'min_balance' => 100,
    ]);

    expect($result->orderId())->toBe(222521420)
        ->and($result->shipmentId())->toBe(222002884)
        ->and($result->awbCode())->toBe('14326421307048')
        ->and($result->courierCompanyId())->toBe(25)
        ->and($result->courierName())->toBe('Xpressbees 5kg')
        ->and($result->labelUrl())->toBe('https://example.test/label.pdf')
        ->and($result->manifestUrl())->toBe('https://example.test/manifest.pdf')
        ->and($result->invoiceUrl())->toBe('https://example.test/invoice.pdf')
        ->and($result->pickupScheduledDate())->toBe('2022-06-04 09:00:00')
        ->and($result->walletBalance())->toBe(500.0)
        ->and($result->toArray()['invoice_generated'])->toBeTrue()
        ->and($result->toArray()['label_generated'])->toBeTrue();

    Shiprocket::assertRequested('GET', '/v1/external/account/details/wallet-balance');
    Shiprocket::assertRequested('POST', '/v1/external/shipments/create/forward-shipment', fn (RecordedRequest $request): bool => $request->payload['order_id'] === '224-447'
        && $request->payload['courier_id'] === 25
        && $request->payload['request_pickup'] === true
        && $request->payload['print_label'] === true
        && $request->payload['generate_manifest'] === true);
    Shiprocket::assertRequested('POST', '/v1/external/orders/print/invoice', fn (RecordedRequest $request): bool => $request->payload === [
        'ids' => [222521420],
    ]);
    Shiprocket::assertNotRequested('GET', '/v1/external/courier/serviceability/');
});

it('refuses to create a shipment when the wallet balance is insufficient', function () {
    Shiprocket::fake([
        '/v1/external/account/details/wallet-balance' => [
            'data' => ['balance_amount' => '10.00'],
        ],
    ]);

    expect(fn () => Shiprocket::fulfill()->create(fulfillmentOrder(), ['min_balance' => 50]))
        ->toThrow(function (InsufficientBalanceException $exception): void {
            expect($exception->balance)->toBe(10.0)
                ->and($exception->required)->toBe(50.0);
        });

    Shiprocket::assertNotRequested('POST', '/v1/external/shipments/create/forward-shipment');
    Shiprocket::assertNotRequested('POST', '/v1/external/orders/print/invoice');
});

it('estimates the required balance from courier rates when min_balance is omitted', function () {
    Shiprocket::fake([
        '/v1/external/account/details/wallet-balance' => [
            'data' => ['balance_amount' => '40.00'],
        ],
        '/v1/external/courier/serviceability/' => [
            'data' => [
                'available_courier_companies' => [
                    ['courier_company_id' => 10, 'rate' => 90, 'cod' => 0],
                    ['courier_company_id' => 25, 'rate' => 54, 'cod' => 0],
                ],
            ],
        ],
    ]);

    expect(fn () => Shiprocket::fulfill()->create(fulfillmentOrder(), [
        'pickup_postcode' => 110030,
    ]))->toThrow(InsufficientBalanceException::class);

    Shiprocket::assertRequested('GET', '/v1/external/courier/serviceability/');
    Shiprocket::assertNotRequested('POST', '/v1/external/shipments/create/forward-shipment');
});

it('throws a pipeline exception with a partial result when invoice generation fails', function () {
    Shiprocket::fake([
        '/v1/external/account/details/wallet-balance' => [
            'data' => ['balance_amount' => '500.00'],
        ],
        '/v1/external/shipments/create/forward-shipment' => [
            'status' => 1,
            'payload' => forwardPayload(),
        ],
        '/v1/external/orders/print/invoice' => [
            '_status' => 500,
            'message' => 'Invoice service unavailable',
        ],
    ]);

    expect(fn () => Shiprocket::fulfill()->create(fulfillmentOrder(), ['min_balance' => 0]))
        ->toThrow(function (ShipmentPipelineException $exception): void {
            expect($exception->step())->toBe('invoice')
                ->and($exception->result()->orderId())->toBe(222521420)
                ->and($exception->result()->shipmentId())->toBe(222002884)
                ->and($exception->result()->awbCode())->toBe('14326421307048')
                ->and($exception->result()->labelUrl())->toBe('https://example.test/label.pdf')
                ->and($exception->result()->invoiceUrl())->toBeNull()
                ->and($exception->getMessage())->toBe('Invoice service unavailable');
        });
});

it('throws a pipeline exception when forward shipment returns status 0', function () {
    Shiprocket::fake([
        '/v1/external/account/details/wallet-balance' => [
            'data' => ['balance_amount' => '500.00'],
        ],
        '/v1/external/shipments/create/forward-shipment' => [
            'status' => 0,
            'payload' => [
                'action' => 'Creating order',
                'error_message' => 'Pickup location not found.',
            ],
        ],
    ]);

    expect(fn () => Shiprocket::fulfill()->create(fulfillmentOrder(), ['min_balance' => 0]))
        ->toThrow(function (ShipmentPipelineException $exception): void {
            expect($exception->step())->toBe('forward')
                ->and($exception->getMessage())->toBe('Pickup location not found.')
                ->and($exception->result()->orderId())->toBeNull();
        });

    Shiprocket::assertNotRequested('POST', '/v1/external/orders/print/invoice');
});

it('skips invoice generation when generate_invoice is false', function () {
    Shiprocket::fake([
        '/v1/external/account/details/wallet-balance' => [
            'data' => ['balance_amount' => '500.00'],
        ],
        '/v1/external/shipments/create/forward-shipment' => [
            'status' => 1,
            'payload' => forwardPayload(),
        ],
    ]);

    $result = Shiprocket::fulfill()->create(fulfillmentOrder(), [
        'min_balance' => 0,
        'generate_invoice' => false,
    ]);

    expect($result->invoiceUrl())->toBeNull()
        ->and($result->toArray()['invoice_generated'])->toBeFalse();

    Shiprocket::assertNotRequested('POST', '/v1/external/orders/print/invoice');
});
