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

    'institution_logos' => [
        // Fetch a wallet's logo right after it is created (see InstitutionLogos).
        'fetch_on_save' => (bool) env('INSTITUTION_LOGOS_FETCH', true),
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
        // Background syncs run at these local hours: every 4 h during the day, 4 a day, which is
        // the PSD2 limit for refreshes without the account holder present. "Sync now" is immediate.
        'sync_hours' => env('ENABLE_BANKING_SYNC_HOURS', '7,11,15,19'),
        'sync_timezone' => env('ENABLE_BANKING_SYNC_TIMEZONE', 'Europe/Ljubljana'),
        'initial_history_days' => (int) env('ENABLE_BANKING_INITIAL_HISTORY_DAYS', 90),
        'jwt_issuer' => env('ENABLE_BANKING_JWT_ISS', 'enablebanking.com'),
        'jwt_audience' => env('ENABLE_BANKING_JWT_AUD', 'api.enablebanking.com'),
    ],

    // UK banks (Enable Banking has no UK coverage in restricted mode).
    'plaid' => [
        'client_id' => env('PLAID_CLIENT_ID'),
        'secret' => env('PLAID_SECRET'),
        'environment' => env('PLAID_ENV', 'production'),
        'country_codes' => array_values(array_filter(array_map(
            fn (string $country): string => strtoupper(trim($country)),
            explode(',', (string) env('PLAID_COUNTRY_CODES', 'GB')),
        ))),
        // Must be listed under Allowed redirect URIs in the Plaid Dashboard (UK banks use OAuth).
        'redirect_uri' => env('PLAID_REDIRECT_URI') ?: rtrim((string) env('APP_URL', ''), '/').'/banking/plaid/oauth',
        'history_days' => (int) env('PLAID_HISTORY_DAYS', 90),
        // Background sync once a day (local time, same timezone as bank syncs).
        'sync_hours' => env('PLAID_SYNC_HOURS', '7'),
        // Free allowance: SpendLess stops syncing a few calls before this. 0 = no limit (paid plan).
        'call_limit' => (int) env('PLAID_CALL_LIMIT', 200),
        'manual_sync_cooldown_minutes' => (int) env('PLAID_MANUAL_SYNC_COOLDOWN_MINUTES', 60),
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
