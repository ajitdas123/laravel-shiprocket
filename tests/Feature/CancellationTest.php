<?php

declare(strict_types=1);

use Madgeek\LaravelShiprocket\Exceptions\OrderHasShipmentException;
use Madgeek\LaravelShiprocket\Exceptions\ShipmentNotCancellableException;
use Madgeek\LaravelShiprocket\Facades\Shiprocket;
use Madgeek\LaravelShiprocket\Testing\RecordedRequest;

it('cancels an order when no awb has been assigned', function () {
    Shiprocket::fake([
        '/v1/external/orders/show/222521420' => [
            'data' => [
                'id' => 222521420,
                'shipments' => [
                    'id' => 222002884,
                    'awb' => null,
                    'status' => 'PENDING',
                ],
            ],
        ],
        '/v1/external/orders/cancel' => ['message' => 'Order cancelled successfully.'],
    ]);

    $result = Shiprocket::cancel()->order(222521420);

    expect($result->orderCanceled)->toBeTrue()
        ->and($result->shipmentCanceled)->toBeFalse()
        ->and($result->orderId)->toBe(222521420)
        ->and($result->awb)->toBeNull();

    Shiprocket::assertRequested('POST', '/v1/external/orders/cancel', fn (RecordedRequest $request): bool => $request->payload === [
        'ids' => [222521420],
    ]);
});

it('refuses to cancel an order that already has a shipment awb', function () {
    Shiprocket::fake([
        '/v1/external/orders/show/222521420' => [
            'data' => [
                'id' => 222521420,
                'shipments' => [
                    'id' => 222002884,
                    'awb' => '14326421307048',
                    'status' => 'AWB ASSIGNED',
                ],
            ],
        ],
    ]);

    expect(fn () => Shiprocket::cancel()->order(222521420))
        ->toThrow(function (OrderHasShipmentException $exception): void {
            expect($exception->orderId)->toBe(222521420)
                ->and($exception->awb)->toBe('14326421307048');
        });

    Shiprocket::assertNotRequested('POST', '/v1/external/orders/cancel');
});

it('cancels a shipment when it is not out for pickup', function () {
    Shiprocket::fake([
        '/v1/external/courier/track/awb/14326421307048' => [
            'tracking_data' => [
                'shipment_status' => 1,
                'shipment_track' => [
                    ['awb_code' => '14326421307048', 'current_status' => 'AWB Assigned'],
                ],
            ],
        ],
        '/v1/external/orders/cancel/shipment/awbs' => [
            'message' => 'Bulk Shipment cancellation is in progress. Please wait for some time.',
        ],
    ]);

    $result = Shiprocket::cancel()->shipment('14326421307048');

    expect($result->shipmentCanceled)->toBeTrue()
        ->and($result->awb)->toBe('14326421307048')
        ->and($result->shipmentStatus)->toBe('AWB Assigned')
        ->and($result->orderCanceled)->toBeFalse();

    Shiprocket::assertRequested('POST', '/v1/external/orders/cancel/shipment/awbs', fn (RecordedRequest $request): bool => $request->payload === [
        'awbs' => ['14326421307048'],
    ]);
});

it('refuses to cancel a shipment that is out for pickup or later', function () {
    Shiprocket::fake([
        '/v1/external/courier/track/awb/14326421307048' => [
            'tracking_data' => [
                'shipment_track' => [
                    ['awb_code' => '14326421307048', 'current_status' => 'Out for Pickup'],
                ],
            ],
        ],
    ]);

    expect(fn () => Shiprocket::cancel()->shipment('14326421307048'))
        ->toThrow(function (ShipmentNotCancellableException $exception): void {
            expect($exception->awb)->toBe('14326421307048')
                ->and($exception->status)->toBe('Out for Pickup');
        });

    Shiprocket::assertNotRequested('POST', '/v1/external/orders/cancel/shipment/awbs');
});

it('cancels shipment then order together', function () {
    Shiprocket::fake([
        '/v1/external/orders/show/222521420' => [
            'data' => [
                'id' => 222521420,
                'shipments' => [
                    'id' => 222002884,
                    'awb' => '14326421307048',
                ],
            ],
        ],
        '/v1/external/courier/track/awb/14326421307048' => [
            'tracking_data' => [
                'shipment_track' => [
                    ['current_status' => 'READY TO SHIP'],
                ],
            ],
        ],
        '/v1/external/orders/cancel/shipment/awbs' => ['message' => 'ok'],
        '/v1/external/orders/cancel' => ['message' => 'ok'],
    ]);

    $result = Shiprocket::cancel()->orderAndShipment(222521420);

    expect($result->toArray())->toBe([
        'order_id' => 222521420,
        'awb' => '14326421307048',
        'order_canceled' => true,
        'shipment_canceled' => true,
        'shipment_status' => 'READY TO SHIP',
    ]);

    Shiprocket::assertRequested('POST', '/v1/external/orders/cancel/shipment/awbs');
    Shiprocket::assertRequested('POST', '/v1/external/orders/cancel');
});

it('cancels only the order in orderAndShipment when no awb exists', function () {
    Shiprocket::fake([
        '/v1/external/orders/show/222521420' => [
            'data' => [
                'id' => 222521420,
                'shipments' => [
                    'id' => 222002884,
                    'awb' => '',
                ],
            ],
        ],
        '/v1/external/orders/cancel' => ['message' => 'ok'],
    ]);

    $result = Shiprocket::cancel()->orderAndShipment(222521420);

    expect($result->orderCanceled)->toBeTrue()
        ->and($result->shipmentCanceled)->toBeFalse()
        ->and($result->awb)->toBeNull();

    Shiprocket::assertNotRequested('POST', '/v1/external/orders/cancel/shipment/awbs');
    Shiprocket::assertRequested('POST', '/v1/external/orders/cancel');
});
