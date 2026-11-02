<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'keycloak' => [
        'base_url' => env('KEYCLOAK_URL', 'http://keycloak:8080/auth'),
        'public_url' => env('KEYCLOAK_PUBLIC_URL', 'http://localhost:8080/auth'),
        'realms' => env('KEYCLOAK_REALM', 'taxvault'),
        'client_id' => env('KEYCLOAK_CLIENT_ID', 'taxvault-web'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect' => env(
            'KEYCLOAK_REDIRECT_URI',
            'http://localhost:8080/api/auth/oidc/callback',
        ),
    ],

];
