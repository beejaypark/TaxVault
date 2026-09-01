<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCR Provider
    |--------------------------------------------------------------------------
    |
    | Supported values:
    | - test
    | - http
    |
    */

    'provider' => env('OCR_PROVIDER', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Generic HTTP OCR Adapter
    |--------------------------------------------------------------------------
    |
    | This configuration intentionally remains provider-neutral.
    | The external service must expose the generic OCR adapter contract.
    |
    */

    'http' => [
        'endpoint' => env('OCR_HTTP_ENDPOINT'),
        'token' => env('OCR_HTTP_TOKEN'),
        'timeout' => (int) env('OCR_HTTP_TIMEOUT', 60),
        'connect_timeout' => (int) env('OCR_HTTP_CONNECT_TIMEOUT', 10),
    ],

];