<div align="center">
    <h1>Laravel Shiprocket</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/ajit-das/laravel-shiprocket"><img src="https://img.shields.io/packagist/v/ajit-das/laravel-shiprocket.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/ajit-das/laravel-shiprocket"><img src="https://img.shields.io/packagist/php-v/ajit-das/laravel-shiprocket.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/ajit-das/laravel-shiprocket"><img src="https://badge.laravel.cloud/badge/ajit-das/laravel-shiprocket?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/ajit-das/laravel-shiprocket/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/ajit-das/laravel-shiprocket/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/ajit-das/laravel-shiprocket"><img src="https://img.shields.io/packagist/dt/ajit-das/laravel-shiprocket.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Laravel client for the Shiprocket API: orders, couriers, serviceability, shipments, labels, manifests, and wallet balance.

## Installation

Requires PHP 8.3+ and Laravel 12 or 13.

You can install the package via Composer:

```bash
composer require ajit-das/laravel-shiprocket
```

Publish the config file:

```bash
php artisan vendor:publish --tag="laravel-shiprocket-config"
```

Create an API user in Shiprocket under **Settings > API > Add New API User**, then set its email and password:

```dotenv
SHIPROCKET_EMAIL=api-user@example.com
SHIPROCKET_PASSWORD=your-api-password
```

That is all you need for authentication. The package logs in with those credentials on the first request, caches the JWT for 9 days (Shiprocket tokens last 10), attaches it to every call, and when a request returns `401` it logs in again and retries once. You do not configure or store a token yourself.

`SHIPROCKET_DRIVER=log` writes requests to the log instead of calling Shiprocket, which is the right setting for local and staging.

## Usage

Every call returns a `Madgeek\LaravelShiprocket\Support\ShiprocketResponse` with `successful`, `status`, `body`, and `message`. Read nested values with `json()`:

```php
use Madgeek\LaravelShiprocket\Facades\Shiprocket;

$balance = Shiprocket::account()->walletBalance()->json('data.balance_amount');
```

Request bodies are the arrays documented by Shiprocket, sent as-is. Methods that take ids accept one id or a list.

### Auth

You normally never call these. Set `SHIPROCKET_EMAIL` and `SHIPROCKET_PASSWORD`, then use `orders()`, `couriers()`, and the other services directly. Auth is handled inside the package.

If you explicitly need the token yourself:

```php
$token = Shiprocket::auth()->getToken(); // from cache, or login + cache when missing
$response = Shiprocket::auth()->login(); // force a fresh login; token is in $response->json('token')
Shiprocket::auth()->logout();            // invalidate with Shiprocket and clear the local cache
```

### Orders

```php
$order = Shiprocket::orders()->create([
    'order_id' => '224-447',
    'order_date' => '2026-09-25 11:11',
    'pickup_location' => 'Primary',
    'billing_customer_name' => 'Naruto',
    'billing_last_name' => 'Uzumaki',
    'billing_address' => 'House 221B, Leaf Village',
    'billing_city' => 'New Delhi',
    'billing_pincode' => 110002,
    'billing_state' => 'Delhi',
    'billing_country' => 'India',
    'billing_email' => 'naruto@example.com',
    'billing_phone' => 9876543210,
    'shipping_is_billing' => true,
    'order_items' => [
        ['name' => 'Kunai', 'sku' => 'chakra123', 'units' => 10, 'selling_price' => 900],
    ],
    'payment_method' => 'Prepaid',
    'sub_total' => 9000,
    'length' => 10,
    'breadth' => 15,
    'height' => 20,
    'weight' => 2.5,
]);

$shipmentId = $order->json('shipment_id');

Shiprocket::orders()->createForChannel([...]);      // POST /orders/create with channel_id
Shiprocket::orders()->update([...]);                // POST /orders/update/adhoc
Shiprocket::orders()->updatePickupLocation([16167171], 'Delhi');
Shiprocket::orders()->updateDeliveryAddress(['order_id' => 16178831, 'shipping_city' => 'Pune', ...]);
Shiprocket::orders()->cancel([16168898, 16167171]);

Shiprocket::orders()->list(['page' => 1, 'per_page' => 50, 'filter_by' => 'status', 'filter' => 'NEW']);
Shiprocket::orders()->find(16167171);
Shiprocket::orders()->export(); // Shiprocket emails a CSV download link
```

### Couriers and serviceability

`serviceability()` returns the full Shiprocket payload. Prefer the helpers when you only need a boolean check or the courier list. `$from` is the pickup pincode and `$to` is the delivery pincode. COD defaults to prepaid (`false`), and weight defaults to `0.5` kg (Shiprocket requires it when no order id is sent).

```php
$serviceable = Shiprocket::couriers()->isServiceable(110030, 122002)->json('serviceable');
$codAvailable = Shiprocket::couriers()->isCodAvailable(110030, 122002)->json('cod_available');
$couriers = Shiprocket::couriers()->availableCouriers(110030, 122002, cod: true, weight: 2)
    ->json('data.available_courier_companies');

// Full Shiprocket payload when you need recommendations and every nested field:
Shiprocket::couriers()->serviceability([
    'pickup_postcode' => 110030,
    'delivery_postcode' => 122002,
    'cod' => 0,
    'weight' => 2,
]);

Shiprocket::couriers()->list('active'); // active, inactive, all, or null

Shiprocket::couriers()->assignAwb($shipmentId);                      // default courier
Shiprocket::couriers()->assignAwb($shipmentId, courierId: 10);
Shiprocket::couriers()->assignAwb($shipmentId, 10, reassign: true);  // once every 24 hours

Shiprocket::couriers()->requestPickup($shipmentId);
Shiprocket::couriers()->requestPickup($shipmentId, ['pickup_date' => ['2026-10-01']]);
Shiprocket::couriers()->requestPickup($shipmentId, ['status' => 'retry']);
```

`isServiceable()` and `isCodAvailable()` keep the usual `ShiprocketResponse` shape with an empty `data` array and a boolean flag (`serviceable` / `cod_available`). `availableCouriers()` returns only `data.available_courier_companies` (rates, ETDs, and the other courier fields Shiprocket sends).

### Shipments

```php
Shiprocket::shipments()->list(['sort' => 'DESC', 'sort_by' => 'id']);
Shiprocket::shipments()->find(16016920);
Shiprocket::shipments()->cancel(['19041211125783']); // AWB numbers
```

### Labels, manifests, and invoices

These return Shiprocket's document URLs, for example `json('label_url')` or `json('manifest_url')`.

```php
Shiprocket::documents()->generateLabel([16104408, 16104409]);
Shiprocket::documents()->generateManifest($shipmentId);
Shiprocket::documents()->printManifest($orderId);
Shiprocket::documents()->generateInvoice($orderId);
Shiprocket::documents()->generateLabelAndInvoice($shipmentIds); // up to 200 shipments
```

### Pickup locations

```php
$locations = Shiprocket::pickupLocations()->list()->json('data.shipping_address');

Shiprocket::pickupLocations()->create([
    'pickup_location' => 'Home', // nickname used as orders.pickup_location
    'name' => 'Deadpool',
    'email' => 'deadpool@chimichanga.com',
    'phone' => '9777777779',
    'address' => 'Mutant Facility, Sector 3',
    'address_2' => '',
    'city' => 'Pune',
    'state' => 'Maharashtra',
    'country' => 'India',
    'pin_code' => '110022',
]);
```

Use the nickname (`pickup_location`) when creating orders. To change an existing order's pickup location, use `Shiprocket::orders()->updatePickupLocation(...)`.

### Wallet balance

```php
Shiprocket::account()->walletBalance()->json('data.balance_amount');
```

Check the configured credentials from the terminal:

```bash
php artisan shiprocket:wallet
```

### Errors and events

Non-2xx responses throw `Madgeek\LaravelShiprocket\Exceptions\RequestFailedException`. Rejected credentials throw `AuthenticationException`. Both extend `ShiprocketException`, and `RequestFailedException::$response` holds the status and body, including Shiprocket's validation `errors`.

Each request dispatches `Madgeek\LaravelShiprocket\Events\RequestSending`, then `RequestSent` or `RequestFailed`.

### Testing

`Shiprocket::fake()` records requests and does not call Shiprocket. Pass response bodies keyed by endpoint to stub results; `*` works as a wildcard:

```php
use Madgeek\LaravelShiprocket\Facades\Shiprocket;
use Madgeek\LaravelShiprocket\Testing\RecordedRequest;

Shiprocket::fake([
    '/v1/external/orders/create/adhoc' => ['order_id' => 281248157, 'shipment_id' => 16090281],
]);

// ... code under test

Shiprocket::assertRequested('POST', '/v1/external/orders/create/adhoc', fn (RecordedRequest $request) => $request->payload['order_id'] === '224-447');
Shiprocket::assertNotRequested('POST', '/v1/external/orders/cancel');
```

`Shiprocket::assertNothingRequested()` checks that no request was sent.

## Contributing

Thank you for considering contributing to Laravel Shiprocket! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Credits

- [Ajit Das](https://github.com/ajit-das)
- [All Contributors](../../contributors)

## License

Laravel Shiprocket is open-sourced software licensed under the [MIT license](LICENSE.md).
