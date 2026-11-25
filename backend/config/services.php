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

    'inbound_email' => [
        'domain' => env('INBOUND_EMAIL_DOMAIN', 'inbox.taxvault.local'),
        'secret' => env('INBOUND_EMAIL_SECRET'),
    ],

    'keycloak' => [
        'base_url' => env('KEYCLOAK_URL', 'http://keycloak:8080/auth'),
        'public_url' => env('KEYCLOAK_PUBLIC_URL', 'http://localhost:8080/auth'),
        'realms' => env('KEYCLOAK_REALM', 'taxvault'),
        'client_id' => env('KEYCLOAK_CLIENT_ID', 'taxvault-web'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'admin_user' => env('KEYCLOAK_ADMIN'),
        'admin_password' => env('KEYCLOAK_ADMIN_PASSWORD'),
        'admin_client_id' => env('KEYCLOAK_ADMIN_CLIENT_ID'),
        'admin_client_secret' => env('KEYCLOAK_ADMIN_CLIENT_SECRET'),
        'redirect' => env(
            'KEYCLOAK_REDIRECT_URI',
            'http://localhost:8080/api/auth/oidc/callback',
        ),
    ],
    'mail_preview_url' => env('MAIL_PREVIEW_URL', 'http://localhost:8025'),

    'microsoft_graph' => [
        'authorize_url' => env('MICROSOFT_GRAPH_AUTHORIZE_URL', 'https://login.microsoftonline.com'),
        'api_url' => env('MICROSOFT_GRAPH_API_URL', 'https://graph.microsoft.com/v1.0'),
        'scopes' => ['openid', 'profile', 'email', 'offline_access', 'User.Read', 'Files.Read'],
    ],

    'google_drive' => [
        'authorize_url' => env('GOOGLE_DRIVE_AUTHORIZE_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
        'token_url' => env('GOOGLE_DRIVE_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
        'userinfo_url' => env('GOOGLE_DRIVE_USERINFO_URL', 'https://openidconnect.googleapis.com/v1/userinfo'),
        'api_url' => env('GOOGLE_DRIVE_API_URL', 'https://www.googleapis.com/drive/v3'),
        'scopes' => ['openid', 'email', 'profile', 'https://www.googleapis.com/auth/drive.readonly'],
    ],

    'dropbox' => [
        'authorize_url' => env('DROPBOX_AUTHORIZE_URL', 'https://www.dropbox.com/oauth2/authorize'),
        'token_url' => env('DROPBOX_TOKEN_URL', 'https://api.dropboxapi.com/oauth2/token'),
        'api_url' => env('DROPBOX_API_URL', 'https://api.dropboxapi.com'),
        'content_url' => env('DROPBOX_CONTENT_URL', 'https://content.dropboxapi.com'),
        'scopes' => ['account_info.read', 'files.metadata.read', 'files.content.read'],
    ],

    'basiq' => [
        'api_key' => env('BASIQ_API_KEY'),
        'base_url' => env('BASIQ_BASE_URL', 'https://au-api.basiq.io'),
        'consent_url' => env('BASIQ_CONSENT_URL', 'https://consent.basiq.io/home'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'tax_news_model' => env('OPENAI_TAX_NEWS_MODEL', 'gpt-4o-mini'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'tax_news_model' => env('ANTHROPIC_TAX_NEWS_MODEL', 'claude-3-5-haiku-latest'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'tax_news_model' => env('GEMINI_TAX_NEWS_MODEL', 'gemini-2.0-flash'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'price_id' => env('STRIPE_PRICE_ID'),
        'monthly_price_id' => env('STRIPE_MONTHLY_PRICE_ID', env('STRIPE_PRICE_ID')),
        'yearly_price_id' => env('STRIPE_YEARLY_PRICE_ID'),
        'public_url' => env('APP_URL', 'http://localhost:4200'),
    ],

];
