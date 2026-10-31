---
name: laravel-shiprocket-development
description: >
  Configure and apply the Laravel Shiprocket package in Laravel applications: create orders,
  fulfill shipments in one call (AWB, label, manifest, invoice), manage pickup locations,
  check courier serviceability, assign AWBs, schedule pickups, generate labels and manifests,
  read wallet balance, and fake Shiprocket in tests.
license: MIT
metadata:
  author: Ajit Das
---

# Laravel Shiprocket

Use this skill when a Laravel application needs to call the Shiprocket API through `ajit-das/laravel-shiprocket`.

## Primary Goal

- apply the package's `Shiprocket` facade in the smallest correct way, and test it with `Shiprocket::fake()`

## Workflow

### 1. Install and configure

- `composer require ajit-das/laravel-shiprocket`
- `php artisan vendor:publish --tag="laravel-shiprocket-config"` publishes `config/shiprocket.php`
- set `SHIPROCKET_EMAIL` and `SHIPROCKET_PASSWORD` for a Shiprocket API user (Settings > API). Do not ask the app to store a Shiprocket token.
- set `SHIPROCKET_DRIVER=log` in local and staging so requests are logged instead of sent
- verify credentials with `php artisan shiprocket:wallet`

Authentication is automatic. The package logs in with email and password, caches the JWT under `shiprocket.token_cache_key` for `shiprocket.token_ttl` seconds (default 9 days; Shiprocket tokens last 10), attaches it to every request, and on `401` logs in again and retries once. Apps should not manage tokens.

### 2. Call the API through the facade

Entry points on `Madgeek\LaravelShiprocket\Facades\Shiprocket`:

- `auth()`: optional only — `getToken()`, `login()`, `logout()`. Other services authenticate themselves.
- `fulfill()`: `create($order, $options)` — wallet check, then forward-shipment (order + AWB + label + manifest + pickup) + invoice; returns `FulfillmentResult`
- `cancel()`: `order($orderId)`, `shipment($awb)`, `orderAndShipment($orderId)` — validated cancels; returns `CancellationResult`
- `orders()`: `create()`, `createForChannel()`, `update()`, `updatePickupLocation($ids, $location)`, `updateDeliveryAddress()`, `cancel($ids)` (raw bulk), `list($filters)`, `find($id)`, `export()`
- `couriers()`: `isServiceable($from, $to, $cod = false, $weight = 0.5)`, `availableCouriers($from, $to, $cod = false, $weight = 0.5)`, `isCodAvailable($from, $to, $weight = 0.5)`, `serviceability($query)` for the full payload, `list($type)`, `assignAwb($shipmentId, $courierId, reassign: bool)`, `requestPickup($shipmentIds, $options)`
- `shipments()`: `list($filters)`, `find($id)`, `cancel($awbs)` (raw bulk)
- `documents()`: `generateLabel()`, `generateManifest()`, `printManifest()`, `generateInvoice()`, `generateLabelAndInvoice()`
- `pickupLocations()`: `list()`, `create($payload)` — account pickup addresses used as `orders.pickup_location`
- `account()`: `walletBalance()`

Order and address bodies are the arrays from the Shiprocket API docs. Id arguments accept one id or a list. Low-level calls return `ShiprocketResponse`; `fulfill()->create()` returns `FulfillmentResult`; `cancel()` returns `CancellationResult`.

### 3. Handle failures

- catch `Madgeek\LaravelShiprocket\Exceptions\ShiprocketException` for everything
- `RequestFailedException::$response` holds the status and body, including Shiprocket validation `errors`
- `AuthenticationException` means the email/password login was rejected or missing
- `InsufficientBalanceException` means the wallet check failed before anything was created (`$e->balance`, `$e->required`)
- `ShipmentPipelineException` means a later fulfill step failed; use `$e->step()` and `$e->result()` for partial IDs/URLs
- `OrderHasShipmentException` means `cancel()->order()` was blocked because an AWB exists; use `orderAndShipment()`
- `ShipmentNotCancellableException` means the AWB is Out for Pickup or later
- listen to `Madgeek\LaravelShiprocket\Events\RequestSending`, `RequestSent`, and `RequestFailed` for logging or metrics

## Rules, References, and Templates

Read before executing:

- no additional resource files for this skill

## Examples

Fulfill an order in one call (preferred):

```php
use Madgeek\LaravelShiprocket\Exceptions\InsufficientBalanceException;
use Madgeek\LaravelShiprocket\Exceptions\ShipmentPipelineException;
use Madgeek\LaravelShiprocket\Facades\Shiprocket;

try {
    $result = Shiprocket::fulfill()->create($payload, [
        'pickup_postcode' => 110030,
        'courier_id' => 10,
    ]);

    $labelUrl = $result->labelUrl();
    $invoiceUrl = $result->invoiceUrl();
} catch (InsufficientBalanceException $e) {
    // top up wallet; nothing created
} catch (ShipmentPipelineException $e) {
    $partial = $e->result(); // may already have order/shipment/awb/label
}

Shiprocket::cancel()->orderAndShipment($result->orderId());
```

Step-by-step when you need finer control:

```php
use Madgeek\LaravelShiprocket\Facades\Shiprocket;

if (! Shiprocket::couriers()->isServiceable(110030, 122002)->json('serviceable')) {
    return;
}

$order = Shiprocket::orders()->create($payload);
$shipmentId = $order->json('shipment_id');

$courierId = Shiprocket::couriers()->availableCouriers(110030, 122002, weight: 2.5)
    ->json('data.available_courier_companies.0.courier_company_id');

$awb = Shiprocket::couriers()->assignAwb($shipmentId, $courierId)->json('response.data.awb_code');
Shiprocket::couriers()->requestPickup($shipmentId);
$labelUrl = Shiprocket::documents()->generateLabel($shipmentId)->json('label_url');
```


Test it without calling Shiprocket:

```php
use Madgeek\LaravelShiprocket\Facades\Shiprocket;
use Madgeek\LaravelShiprocket\Testing\RecordedRequest;

Shiprocket::fake([
    '/v1/external/orders/create/adhoc' => ['order_id' => 281248157, 'shipment_id' => 16090281],
    '/v1/external/courier/*' => ['status' => 1],
]);

// run the code under test

Shiprocket::assertRequested('POST', '/v1/external/courier/assign/awb', fn (RecordedRequest $request) => $request->payload['shipment_id'] === 16090281);
```

## Anti-patterns

- do not call `Http` against `apiv2.shiprocket.in` directly; use the facade so tokens, retries, events, and fakes apply
- do not require the app to set a Shiprocket token env var; only email and password
- do not pass email or password to `auth()->login()` or `getToken()`; the package reads them from config
- do not store or refresh the Shiprocket token in app code; call package methods and let it cache and retry
- do not use `Http::fake()` to test app code that uses this package; use `Shiprocket::fake()`
