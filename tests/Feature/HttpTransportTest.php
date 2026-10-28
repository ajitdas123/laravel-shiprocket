<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Madgeek\LaravelShiprocket\Events\RequestFailed;
use Madgeek\LaravelShiprocket\Events\RequestSending;
use Madgeek\LaravelShiprocket\Events\RequestSent;
use Madgeek\LaravelShiprocket\Exceptions\AuthenticationException;
use Madgeek\LaravelShiprocket\Exceptions\RequestFailedException;
use Madgeek\LaravelShiprocket\Facades\Shiprocket;

const LOGIN_URL = 'https://apiv2.shiprocket.in/v1/external/auth/login';
const WALLET_URL = 'https://apiv2.shiprocket.in/v1/external/account/details/wallet-balance';

it('logs in once and reuses the cached token', function () {
    Http::fake([
        LOGIN_URL => Http::response(['token' => 'jwt-1'], 200),
        WALLET_URL => Http::response(['data' => ['balance_amount' => '250.00']], 200),
    ]);
    useHttpDriver();

    $first = Shiprocket::account()->walletBalance();
    Shiprocket::account()->walletBalance();

    expect($first->json('data.balance_amount'))->toBe('250.00')
        ->and(Cache::get('shiprocket.token'))->toBe('jwt-1');

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => $request->url() === LOGIN_URL
        && $request->method() === 'POST'
        && $request['email'] === 'api@example.com'
        && $request['password'] === 'secret'
        && ! $request->hasHeader('Authorization'));
    Http::assertSent(fn (Request $request): bool => $request->url() === WALLET_URL
        && $request->method() === 'GET'
        && $request->hasHeader('Authorization', 'Bearer jwt-1')
        && $request->hasHeader('Accept', 'application/json'));
});

it('sends json bodies and query strings', function () {
    Cache::put('shiprocket.token', 'cached-jwt');
    Http::fake([
        '*' => Http::response(['status' => 1], 200),
    ]);
    useHttpDriver();

    Shiprocket::orders()->create(['order_id' => '224-447', 'pickup_location' => 'Jammu']);
    Shiprocket::couriers()->serviceability(['pickup_postcode' => 110030, 'delivery_postcode' => 122002, 'cod' => 0, 'weight' => 2]);
    Shiprocket::orders()->updatePickupLocation(16167171, 'Delhi');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://apiv2.shiprocket.in/v1/external/orders/create/adhoc'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer cached-jwt')
        && $request['order_id'] === '224-447');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://apiv2.shiprocket.in/v1/external/courier/serviceability/?pickup_postcode=110030&delivery_postcode=122002&cod=0&weight=2'
        && $request->method() === 'GET');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://apiv2.shiprocket.in/v1/external/orders/address/pickup'
        && $request->method() === 'PATCH'
        && $request['order_id'] === [16167171]);
    Http::assertNotSent(fn (Request $request): bool => $request->url() === LOGIN_URL);
});

it('returns a cached token from getToken without logging in again', function () {
    Cache::put('shiprocket.token', 'cached-jwt');
    Http::fake();
    useHttpDriver();

    expect(Shiprocket::auth()->getToken())->toBe('cached-jwt');

    Http::assertNothingSent();
});

it('logs in through getToken when the cache is empty', function () {
    Http::fake([
        LOGIN_URL => Http::response(['token' => 'jwt-from-get-token'], 200),
    ]);
    useHttpDriver();

    expect(Shiprocket::auth()->getToken())->toBe('jwt-from-get-token')
        ->and(Cache::get('shiprocket.token'))->toBe('jwt-from-get-token');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === LOGIN_URL
        && $request['email'] === 'api@example.com'
        && $request['password'] === 'secret');
});

it('logs in again and retries once when the cached token is rejected', function () {
    Cache::put('shiprocket.token', 'expired-jwt');
    Http::fake([
        LOGIN_URL => Http::response(['token' => 'fresh-jwt'], 200),
        WALLET_URL => Http::sequence()
            ->push(['message' => 'Token has expired'], 401)
            ->push(['data' => ['balance_amount' => '10.00']], 200),
    ]);
    useHttpDriver();

    $response = Shiprocket::account()->walletBalance();

    expect($response->json('data.balance_amount'))->toBe('10.00')
        ->and(Cache::get('shiprocket.token'))->toBe('fresh-jwt');

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => $request->url() === WALLET_URL && $request->hasHeader('Authorization', 'Bearer fresh-jwt'));
});

it('throws an authentication exception when the retry is also rejected', function () {
    Http::fake([
        LOGIN_URL => Http::response(['token' => 'jwt'], 200),
        WALLET_URL => Http::response(['message' => 'Unauthenticated.'], 401),
    ]);
    useHttpDriver();

    expect(fn () => Shiprocket::account()->walletBalance())->toThrow(AuthenticationException::class, 'Unauthenticated.');

    Http::assertSentCount(4);
});

it('throws when credentials are missing', function () {
    Http::fake();
    useHttpDriver(['shiprocket.email' => null, 'shiprocket.password' => null]);

    expect(fn () => Shiprocket::account()->walletBalance())
        ->toThrow(AuthenticationException::class, 'Shiprocket email and password are not configured.');

    Http::assertNothingSent();
});

it('throws when shiprocket rejects the login', function () {
    Http::fake([
        LOGIN_URL => Http::response(['message' => 'Invalid email and password combination'], 400),
    ]);
    useHttpDriver();

    expect(fn () => Shiprocket::auth()->login())
        ->toThrow(AuthenticationException::class, 'Invalid email and password combination');
});

it('throws when the login response has no token', function () {
    Http::fake([
        LOGIN_URL => Http::response(['id' => 1], 200),
    ]);
    useHttpDriver();

    expect(fn () => Shiprocket::auth()->login())
        ->toThrow(AuthenticationException::class, 'Shiprocket did not return an auth token.');
});

it('logs in explicitly and forgets the token on logout', function () {
    Http::fake([
        LOGIN_URL => Http::response(['token' => 'jwt-2', 'email' => 'api@example.com'], 200),
        '*' => Http::response([], 200),
    ]);
    useHttpDriver();

    $login = Shiprocket::auth()->login();

    expect($login->json('token'))->toBe('jwt-2')
        ->and(Cache::get('shiprocket.token'))->toBe('jwt-2')
        ->and(Shiprocket::auth()->getToken())->toBe('jwt-2');

    Shiprocket::auth()->logout();

    expect(Cache::has('shiprocket.token'))->toBeFalse();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://apiv2.shiprocket.in/v1/external/auth/logout'
        && $request->hasHeader('Authorization', 'Bearer jwt-2'));
});

it('throws a request failed exception with the response on errors', function () {
    Cache::put('shiprocket.token', 'jwt');
    Http::fake([
        '*' => Http::response(['message' => 'Oops! Invalid Data.', 'errors' => ['order_id' => ['required']]], 422),
    ]);
    useHttpDriver();

    try {
        Shiprocket::orders()->create([]);
        $this->fail('Expected a request failure.');
    } catch (RequestFailedException $exception) {
        expect($exception->getMessage())->toBe('Oops! Invalid Data.')
            ->and($exception->response->status)->toBe(422)
            ->and($exception->response->json('errors.order_id.0'))->toBe('required');
    }
});

it('falls back to the raw body when the response is not json', function () {
    Cache::put('shiprocket.token', 'jwt');
    Http::fake([
        '*' => Http::response('Bad Gateway', 502),
    ]);
    useHttpDriver();

    expect(fn () => Shiprocket::account()->walletBalance())->toThrow(RequestFailedException::class, 'Bad Gateway');
});

it('wraps connection failures', function () {
    Cache::put('shiprocket.token', 'jwt');
    Http::fake(function (): never {
        throw new ConnectionException('Could not resolve host.');
    });
    useHttpDriver(['shiprocket.http.retry' => 2]);

    expect(fn () => Shiprocket::account()->walletBalance())->toThrow(RequestFailedException::class, 'Could not resolve host.');
});

it('dispatches sending and sent events', function () {
    Event::fake([RequestSending::class, RequestSent::class, RequestFailed::class]);
    Cache::put('shiprocket.token', 'jwt');
    Http::fake(['*' => Http::response(['data' => ['balance_amount' => '5']], 200)]);
    useHttpDriver();

    Shiprocket::account()->walletBalance();

    Event::assertDispatched(RequestSending::class, fn (RequestSending $event): bool => $event->method === 'GET'
        && $event->endpoint === '/v1/external/account/details/wallet-balance');
    Event::assertDispatched(RequestSent::class, fn (RequestSent $event): bool => $event->response->status === 200);
    Event::assertNotDispatched(RequestFailed::class);
});

it('dispatches a failed event when the request throws', function () {
    Event::fake([RequestSending::class, RequestSent::class, RequestFailed::class]);
    Cache::put('shiprocket.token', 'jwt');
    Http::fake(['*' => Http::response(['message' => 'Server error'], 500)]);
    useHttpDriver();

    expect(fn () => Shiprocket::shipments()->find(1))->toThrow(RequestFailedException::class);

    Event::assertDispatched(RequestFailed::class, fn (RequestFailed $event): bool => $event->exception instanceof RequestFailedException);
    Event::assertNotDispatched(RequestSent::class);
});
