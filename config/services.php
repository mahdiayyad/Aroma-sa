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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment gateways (Saudi market)
    |--------------------------------------------------------------------------
    */
    'moyasar' => [
        'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY'),
        'secret_key'      => env('MOYASAR_SECRET_KEY'),
        'webhook_secret'  => env('MOYASAR_WEBHOOK_SECRET'),
    ],

    'tabby' => [
        'public_key'     => env('TABBY_PUBLIC_KEY'),
        'secret_key'     => env('TABBY_SECRET_KEY'),
        'merchant_code'  => env('TABBY_MERCHANT_CODE'),
        'webhook_secret' => env('TABBY_WEBHOOK_SECRET'),
        'base_url'       => env('TABBY_BASE_URL', 'https://api.tabby.ai'),
    ],

    'tamara' => [
        'api_token'          => env('TAMARA_API_TOKEN'),
        'notification_token' => env('TAMARA_NOTIFICATION_TOKEN'),
        'base_url'           => env('TAMARA_BASE_URL', 'https://api-sandbox.tamara.co'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification providers (SMS + WhatsApp)
    |--------------------------------------------------------------------------
    */
    'sms' => [
        'provider'  => env('SMS_PROVIDER', 'unifonic'),
        'api_key'   => env('SMS_API_KEY'),
        'sender_id' => env('SMS_SENDER_ID', 'Aroma'),
    ],

    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'meta'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
        'token'    => env('WHATSAPP_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI assistant (Google Gemini)
    |--------------------------------------------------------------------------
    | Powers the storefront live-chat concierge. Without an API key the widget
    | degrades to a graceful hand-off message + WhatsApp, never an error.
    */
    'gemini' => [
        'api_key'     => env('GEMINI_API_KEY'),
        // Both legacy "AIza…" keys and the newer AI Studio "AQ…" auth keys work
        // with the x-goog-api-key header — never validate a key by its prefix.
        // "…-latest" aliases track whatever the project actually has quota for.
        // Note: full (non-lite) flash models reason internally and can spend the
        // whole token budget before emitting text — raise GEMINI_MAX_TOKENS if
        // you switch to one.
        'model'       => env('GEMINI_MODEL', 'gemini-flash-lite-latest'),
        'base_url'    => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'max_tokens'  => (int) env('GEMINI_MAX_TOKENS', 500),
        'temperature' => (float) env('GEMINI_TEMPERATURE', 0.7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maps (address location picker)
    |--------------------------------------------------------------------------
    | Powers the map tiles on account/addresses/form.blade.php. Without a
    | token the picker still fully works — it just falls back to the free
    | OpenStreetMap tile style instead of Mapbox's more polished basemap.
    | Get a free public token at https://account.mapbox.com/access-tokens/.
    */
    'mapbox' => [
        'access_token' => env('MAPBOX_ACCESS_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | National Address (Saudi Post / SPL) location-code lookup
    |--------------------------------------------------------------------------
    | Resolves a Saudi National Address short code (AAAA1234) to coordinates +
    | city/region/district via LocationLookupService. Leave base_url/api_key
    | blank to run in stub mode (deterministic canned responses, so checkout
    | and account addresses keep working before real SPL credentials exist).
    */
    'national_address' => [
        'base_url' => env('NATIONAL_ADDRESS_BASE_URL', ''),
        'api_key'  => env('NATIONAL_ADDRESS_API_KEY', ''),
        'timeout'  => (int) env('NATIONAL_ADDRESS_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Social login
    |--------------------------------------------------------------------------
    */
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

];
