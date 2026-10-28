<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "http", "log"
    |
    */

    'driver' => env('SHIPROCKET_DRIVER', 'http'),

    /*
    |--------------------------------------------------------------------------
    | API User
    |--------------------------------------------------------------------------
    |
    | Credentials of the API user created under Settings > API in Shiprocket.
    | The package logs in with these credentials, caches the JWT, and refreshes
    | it automatically. You do not need to manage or configure a token.
    |
    */

    'email' => env('SHIPROCKET_EMAIL'),

    'password' => env('SHIPROCKET_PASSWORD'),

    'base_url' => env('SHIPROCKET_BASE_URL', 'https://apiv2.shiprocket.in'),

    'http' => [
        'timeout' => (int) env('SHIPROCKET_HTTP_TIMEOUT', 30),
        'retry' => (int) env('SHIPROCKET_HTTP_RETRY', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Cache
    |--------------------------------------------------------------------------
    |
    | Shiprocket tokens are valid for 10 days. The package caches them under
    | this key for token_ttl seconds (default 9 days) and regenerates them
    | on expiry or a 401. You normally leave these defaults alone.
    |
    */

    'token_cache_key' => env('SHIPROCKET_TOKEN_CACHE_KEY', 'shiprocket.token'),

    'token_ttl' => (int) env('SHIPROCKET_TOKEN_TTL', 777600),

];
