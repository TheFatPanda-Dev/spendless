<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    'enable_banking' => [
        'base_url' => env('ENABLE_BANKING_BASE_URL', 'https://api.enablebanking.com'),
        'app_id' => env('ENABLE_BANKING_APP_ID'),
        'private_key_path' => env('ENABLE_BANKING_PRIVATE_KEY_PATH'),
        'country' => env('ENABLE_BANKING_COUNTRY', 'SI'),
        'consent_days' => (int) env('ENABLE_BANKING_CONSENT_DAYS', 10),
        'sync_interval_minutes' => (int) env('ENABLE_BANKING_SYNC_INTERVAL_MINUTES', 60),
        'jwt_issuer' => env('ENABLE_BANKING_JWT_ISS', 'enablebanking.com'),
        'jwt_audience' => env('ENABLE_BANKING_JWT_AUD', 'api.enablebanking.com'),
    ],

    'plaid' => [
        'base_url' => env('PLAID_BASE_URL', 'https://sandbox.plaid.com'),
        'client_id' => env('PLAID_CLIENT_ID'),
        'secret' => env('PLAID_SECRET'),
        'environment' => env('PLAID_ENV', 'sandbox'),
        'version' => env('PLAID_VERSION', '2020-09-14'),
        'country_codes' => array_filter(array_map('trim', explode(',', (string) env('PLAID_COUNTRY_CODES', 'US')))),
        'language' => env('PLAID_LANGUAGE', 'en'),
        'webhook_url' => env('PLAID_WEBHOOK_URL'),
        'webhook_secret' => env('PLAID_WEBHOOK_SECRET'),
        'redirect_uri' => env('PLAID_REDIRECT_URI'),
        'initial_days_requested' => (int) env('PLAID_INITIAL_DAYS_REQUESTED', 730),
    ],

    'freecurrencyapi' => [
        'base_url' => env('FREECURRENCYAPI_BASE_URL', 'https://api.freecurrencyapi.com'),
        'key' => env('FREECURRENCYAPI_KEY'),
        'timeout_seconds' => (int) env('FREECURRENCYAPI_TIMEOUT_SECONDS', 8),
        'cache_ttl_seconds' => (int) env('FREECURRENCYAPI_CACHE_TTL_SECONDS', 3600),
        'stale_cache_ttl_seconds' => (int) env('FREECURRENCYAPI_STALE_CACHE_TTL_SECONDS', 604800),
        'max_requests_per_minute' => (int) env('FREECURRENCYAPI_MAX_REQUESTS_PER_MINUTE', 10),
        'monthly_request_limit' => (int) env('FREECURRENCYAPI_MONTHLY_REQUEST_LIMIT', 1000),
        'monthly_reserve' => (int) env('FREECURRENCYAPI_MONTHLY_RESERVE', 0),
    ],

];
