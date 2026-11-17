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
        // Countries offered in the bank picker (ISO 3166 alpha-2), first one is the default.
        'countries' => array_values(array_filter(array_map(
            fn (string $country): string => strtoupper(trim($country)),
            explode(',', (string) env('ENABLE_BANKING_COUNTRIES', 'SI,GB')),
        ))),
        // Consent length asked from the bank; capped by each bank's own maximum.
        'max_consent_days' => (int) env('ENABLE_BANKING_MAX_CONSENT_DAYS', 180),
        // Background syncs run in fixed slots (6 h = 00/06/12/18), i.e. 4 a day, which is
        // the PSD2 limit for refreshes without the account holder present.
        'sync_interval_hours' => (int) env('ENABLE_BANKING_SYNC_INTERVAL_HOURS', 6),
        'initial_history_days' => (int) env('ENABLE_BANKING_INITIAL_HISTORY_DAYS', 90),
        'jwt_issuer' => env('ENABLE_BANKING_JWT_ISS', 'enablebanking.com'),
        'jwt_audience' => env('ENABLE_BANKING_JWT_AUD', 'api.enablebanking.com'),
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
