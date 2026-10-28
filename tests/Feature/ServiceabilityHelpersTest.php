<?php

declare(strict_types=1);

use Madgeek\LaravelShiprocket\Facades\Shiprocket;
use Madgeek\LaravelShiprocket\Testing\RecordedRequest;

it('reports a route as serviceable without returning courier data', function () {
    Shiprocket::fake([
        '/v1/external/courier/serviceability/' => [
            'data' => [
                'available_courier_companies' => [
                    ['courier_company_id' => 43, 'courier_name' => 'Delhivery Surface', 'rate' => 54, 'cod' => 1],
                ],
            ],
        ],
    ]);

    $response = Shiprocket::couriers()->isServiceable(110030, 122002);

    expect($response->json('serviceable'))->toBeTrue()
        ->and($response->json('data'))->toBe([]);

    Shiprocket::assertRequested('GET', '/v1/external/courier/serviceability/', fn (RecordedRequest $request): bool => $request->query === [
        'pickup_postcode' => 110030,
        'delivery_postcode' => 122002,
        'cod' => 0,
        'weight' => 0.5,
    ]);
});

it('reports a route as not serviceable when no couriers are returned', function () {
    Shiprocket::fake([
        '/v1/external/courier/serviceability/' => [
            'data' => [
                'available_courier_companies' => [],
            ],
        ],
    ]);

    $response = Shiprocket::couriers()->isServiceable('110030', '122002', cod: true, weight: 2);

    expect($response->json('serviceable'))->toBeFalse()
        ->and($response->json('data'))->toBe([]);

    Shiprocket::assertRequested('GET', '/v1/external/courier/serviceability/', fn (RecordedRequest $request): bool => $request->query === [
        'pickup_postcode' => '110030',
        'delivery_postcode' => '122002',
        'cod' => 1,
        'weight' => 2,
    ]);
});

it('returns available couriers and rates for the route', function () {
    $couriers = [
        ['courier_company_id' => 43, 'courier_name' => 'Delhivery Surface', 'rate' => 54, 'cod' => 1],
        ['courier_company_id' => 10, 'courier_name' => 'Bluedart', 'rate' => 90, 'cod' => 0],
    ];

    Shiprocket::fake([
        '/v1/external/courier/serviceability/' => [
            'currency' => 'INR',
            'data' => [
                'available_courier_companies' => $couriers,
                'recommended_courier_company_id' => 43,
            ],
        ],
    ]);

    $response = Shiprocket::couriers()->availableCouriers(110030, 122002, cod: true, weight: 1);

    expect($response->json('data.available_courier_companies'))->toBe($couriers)
        ->and($response->json('data.recommended_courier_company_id'))->toBeNull()
        ->and($response->json('currency'))->toBeNull();

    Shiprocket::assertRequested('GET', '/v1/external/courier/serviceability/', fn (RecordedRequest $request): bool => $request->query === [
        'pickup_postcode' => 110030,
        'delivery_postcode' => 122002,
        'cod' => 1,
        'weight' => 1,
    ]);
});

it('reports whether cod is available for the route', function () {
    Shiprocket::fake([
        '/v1/external/courier/serviceability/' => [
            'data' => [
                'available_courier_companies' => [
                    ['courier_company_id' => 43, 'courier_name' => 'Delhivery Surface', 'rate' => 54, 'cod' => 1],
                    ['courier_company_id' => 10, 'courier_name' => 'Bluedart', 'rate' => 90, 'cod' => 0],
                ],
            ],
        ],
    ]);

    $response = Shiprocket::couriers()->isCodAvailable(110030, 122002, weight: 1.5);

    expect($response->json('cod_available'))->toBeTrue()
        ->and($response->json('data'))->toBe([]);

    Shiprocket::assertRequested('GET', '/v1/external/courier/serviceability/', fn (RecordedRequest $request): bool => $request->query === [
        'pickup_postcode' => 110030,
        'delivery_postcode' => 122002,
        'cod' => 1,
        'weight' => 1.5,
    ]);
});

it('reports cod as unavailable when no cod courier is returned', function () {
    Shiprocket::fake([
        '/v1/external/courier/serviceability/' => [
            'data' => [
                'available_courier_companies' => [
                    ['courier_company_id' => 10, 'courier_name' => 'Bluedart', 'rate' => 90, 'cod' => 0],
                ],
            ],
        ],
    ]);

    expect(Shiprocket::couriers()->isCodAvailable(110030, 122002)->json('cod_available'))->toBeFalse();
});

it('treats a missing courier list as not serviceable and not cod capable', function () {
    Shiprocket::fake([
        '/v1/external/courier/serviceability/' => ['message' => 'ok'],
    ]);

    expect(Shiprocket::couriers()->isServiceable(110030, 122002)->json('serviceable'))->toBeFalse()
        ->and(Shiprocket::couriers()->isCodAvailable(110030, 122002)->json('cod_available'))->toBeFalse()
        ->and(Shiprocket::couriers()->availableCouriers(110030, 122002)->json('data.available_courier_companies'))->toBe([]);
});
