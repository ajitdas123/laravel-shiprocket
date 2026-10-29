<?php

declare(strict_types=1);

use Madgeek\LaravelShiprocket\Facades\Shiprocket;
use Madgeek\LaravelShiprocket\Testing\RecordedRequest;

beforeEach(function () {
    Shiprocket::fake();
});

it('sends auth requests without exposing credentials in the payload', function () {
    expect(Shiprocket::auth()->getToken())->toBe('faked');

    Shiprocket::auth()->login();
    Shiprocket::auth()->logout();

    Shiprocket::assertRequested('POST', '/v1/external/auth/login', fn (RecordedRequest $request): bool => $request->payload === []);
    Shiprocket::assertRequested('POST', '/v1/external/auth/logout');
});

it('creates and updates orders with the given payload', function () {
    $order = ['order_id' => '224-447', 'pickup_location' => 'Jammu'];

    Shiprocket::orders()->create($order);
    Shiprocket::orders()->createForChannel([...$order, 'channel_id' => 27022]);
    Shiprocket::orders()->update($order);
    Shiprocket::orders()->updateDeliveryAddress(['order_id' => 16178831, 'shipping_city' => 'Pune']);

    Shiprocket::assertRequested('POST', '/v1/external/orders/create/adhoc', fn (RecordedRequest $request): bool => $request->payload === $order);
    Shiprocket::assertRequested('POST', '/v1/external/orders/create', fn (RecordedRequest $request): bool => $request->payload['channel_id'] === 27022);
    Shiprocket::assertRequested('POST', '/v1/external/orders/update/adhoc');
    Shiprocket::assertRequested('POST', '/v1/external/orders/address/update', fn (RecordedRequest $request): bool => $request->payload['shipping_city'] === 'Pune');
});

it('wraps single order ids in lists', function () {
    Shiprocket::orders()->updatePickupLocation(16167171, 'Delhi');
    Shiprocket::orders()->cancel([16168898, 16167171]);

    Shiprocket::assertRequested('PATCH', '/v1/external/orders/address/pickup', fn (RecordedRequest $request): bool => $request->payload === [
        'order_id' => [16167171],
        'pickup_location' => 'Delhi',
    ]);
    Shiprocket::assertRequested('POST', '/v1/external/orders/cancel', fn (RecordedRequest $request): bool => $request->payload === [
        'ids' => [16168898, 16167171],
    ]);
});

it('lists finds and exports orders', function () {
    Shiprocket::orders()->list(['page' => 2, 'per_page' => 50]);
    Shiprocket::orders()->find(16167171);
    Shiprocket::orders()->export();

    Shiprocket::assertRequested('GET', '/v1/external/orders', fn (RecordedRequest $request): bool => $request->query === ['page' => 2, 'per_page' => 50]);
    Shiprocket::assertRequested('GET', '/v1/external/orders/show/16167171');
    Shiprocket::assertRequested('POST', '/v1/external/orders/export');
});

it('lists couriers with an optional type', function () {
    Shiprocket::couriers()->list();
    Shiprocket::couriers()->list('active');

    Shiprocket::assertRequested('GET', '/v1/external/courier/courierListWithCounts', fn (RecordedRequest $request): bool => $request->query === []);
    Shiprocket::assertRequested('GET', '/v1/external/courier/courierListWithCounts', fn (RecordedRequest $request): bool => $request->query === ['type' => 'active']);
});

it('checks courier serviceability with query parameters', function () {
    $query = ['pickup_postcode' => 110030, 'delivery_postcode' => 122002, 'cod' => 0, 'weight' => 2];

    Shiprocket::couriers()->serviceability($query);

    Shiprocket::assertRequested('GET', '/v1/external/courier/serviceability/', fn (RecordedRequest $request): bool => $request->query === $query && $request->payload === []);
});

it('assigns awbs and requests pickups', function () {
    Shiprocket::couriers()->assignAwb(16090281);
    Shiprocket::couriers()->assignAwb(16090282, 10, reassign: true);
    Shiprocket::couriers()->requestPickup(16091084, ['pickup_date' => ['2026-10-01']]);

    Shiprocket::assertRequested('POST', '/v1/external/courier/assign/awb', fn (RecordedRequest $request): bool => $request->payload === ['shipment_id' => 16090281]);
    Shiprocket::assertRequested('POST', '/v1/external/courier/assign/awb', fn (RecordedRequest $request): bool => $request->payload === [
        'shipment_id' => 16090282,
        'courier_id' => 10,
        'status' => 'reassign',
    ]);
    Shiprocket::assertRequested('POST', '/v1/external/courier/generate/pickup', fn (RecordedRequest $request): bool => $request->payload === [
        'shipment_id' => [16091084],
        'pickup_date' => ['2026-10-01'],
    ]);
});

it('lists finds and cancels shipments', function () {
    Shiprocket::shipments()->list(['filter_by' => 'id', 'filter' => '141121']);
    Shiprocket::shipments()->find(16016920);
    Shiprocket::shipments()->cancel('19041211125783');

    Shiprocket::assertRequested('GET', '/v1/external/shipments', fn (RecordedRequest $request): bool => $request->query['filter'] === '141121');
    Shiprocket::assertRequested('GET', '/v1/external/shipments/16016920');
    Shiprocket::assertRequested('POST', '/v1/external/orders/cancel/shipment/awbs', fn (RecordedRequest $request): bool => $request->payload === ['awbs' => ['19041211125783']]);
});

it('tracks a shipment by awb', function () {
    Shiprocket::shipments()->trackByAwb('19041211125783');

    Shiprocket::assertRequested('GET', '/v1/external/courier/track/awb/19041211125783');
});

it('generates labels manifests and invoices', function () {
    Shiprocket::documents()->generateLabel([16104408, 16104409]);
    Shiprocket::documents()->generateManifest(16090109);
    Shiprocket::documents()->printManifest(16240904);
    Shiprocket::documents()->generateInvoice(16255275);
    Shiprocket::documents()->generateLabelAndInvoice([123456789, 123456790]);

    Shiprocket::assertRequested('POST', '/v1/external/courier/generate/label', fn (RecordedRequest $request): bool => $request->payload === ['shipment_id' => [16104408, 16104409]]);
    Shiprocket::assertRequested('POST', '/v1/external/manifests/generate', fn (RecordedRequest $request): bool => $request->payload === ['shipment_id' => [16090109]]);
    Shiprocket::assertRequested('POST', '/v1/external/manifests/print', fn (RecordedRequest $request): bool => $request->payload === ['order_ids' => [16240904]]);
    Shiprocket::assertRequested('POST', '/v1/external/orders/print/invoice', fn (RecordedRequest $request): bool => $request->payload === ['ids' => [16255275]]);
    Shiprocket::assertRequested('POST', '/v1/external/courier/generate/label-invoice', fn (RecordedRequest $request): bool => $request->payload === ['shipment_ids' => [123456789, 123456790]]);
});

it('lists and creates pickup locations', function () {
    $location = [
        'pickup_location' => 'Home',
        'name' => 'Deadpool',
        'email' => 'deadpool@chimichanga.com',
        'phone' => '9777777779',
        'address' => 'Mutant Facility, Sector 3',
        'address_2' => '',
        'city' => 'Pune',
        'state' => 'Maharashtra',
        'country' => 'India',
        'pin_code' => '110022',
    ];

    Shiprocket::pickupLocations()->list();
    Shiprocket::pickupLocations()->create($location);

    Shiprocket::assertRequested('GET', '/v1/external/settings/company/pickup');
    Shiprocket::assertRequested('POST', '/v1/external/settings/company/addpickup', fn (RecordedRequest $request): bool => $request->payload === $location);
});

it('fetches the wallet balance', function () {
    Shiprocket::account()->walletBalance();

    Shiprocket::assertRequested('GET', '/v1/external/account/details/wallet-balance');
});
