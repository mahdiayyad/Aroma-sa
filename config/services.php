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
        // Widgets (PDP / cart / checkout). Sandbox and production use
        // different public keys; the script host follows the API base URL
        // unless TAMARA_WIDGET_URL overrides it.
        'public_key'         => env('TAMARA_PUBLIC_KEY'),
        'widget_url'         => env('TAMARA_WIDGET_URL'),
        // Required by Tamara's Capture API (shipping_info.shipping_company).
        'shipping_company'   => env('TAMARA_SHIPPING_COMPANY', 'Aroma Delivery'),
        // Pre-checkout eligibility is fail-open: if Tamara doesn't answer
        // inside this window the option is shown normally.
        'eligibility_timeout_ms' => (int) env('TAMARA_ELIGIBILITY_TIMEOUT_MS', 800),
    ],

    'aramex' => [
        // Sandbox by default; production is https://ws.aramex.net/ShippingAPI.V2
        'base_url'             => env('ARAMEX_BASE_URL', 'https://ws.dev.aramex.net/ShippingAPI.V2'),
        'username'             => env('ARAMEX_USERNAME'),
        'password'             => env('ARAMEX_PASSWORD'),
        'account_number'       => env('ARAMEX_ACCOUNT_NUMBER'),
        'account_pin'          => env('ARAMEX_ACCOUNT_PIN'),
        'account_entity'       => env('ARAMEX_ACCOUNT_ENTITY', 'RUH'),
        'account_country_code' => env('ARAMEX_ACCOUNT_COUNTRY_CODE', 'SA'),
        'version'              => env('ARAMEX_API_VERSION', 'v1.0'),
        'source'               => (int) env('ARAMEX_SOURCE', 24),
        'timeout'              => (int) env('ARAMEX_TIMEOUT', 25),
        'currency'             => env('ARAMEX_CURRENCY', 'SAR'),

        // Shared secret Aramex must send (X-Aramex-Token header or ?token=)
        // with every push tracking update. Unset = the webhook rejects all.
        'webhook_secret'       => env('ARAMEX_WEBHOOK_SECRET'),

        // Products carry no weight yet, so each unit counts as this many kg.
        'default_item_weight_kg' => (float) env('ARAMEX_DEFAULT_ITEM_WEIGHT_KG', 1.0),
        'description_of_goods'   => env('ARAMEX_DESCRIPTION_OF_GOODS', 'Apparel and gifts'),

        // Labels hold customer names, phones and addresses: private disk only.
        'label_disk'           => env('ARAMEX_LABEL_DISK', 'local'),
        'label_report_id'      => (int) env('ARAMEX_LABEL_REPORT_ID', 9201),
        // Label PDFs are only downloaded from these hosts (and subdomains).
        'label_hosts'          => ['aramex.net', 'aramex.com'],

        'domestic' => [
            'product_group' => 'DOM',
            'product_type'  => env('ARAMEX_DOMESTIC_PRODUCT_TYPE', 'ONP'),
        ],
        'international' => [
            'product_group' => 'EXP',
            'product_type'  => env('ARAMEX_INTERNATIONAL_PRODUCT_TYPE', 'PPX'),
        ],

        // Aramex tracking UpdateCode => internal shipping status. Codes not
        // listed here are classified from their description instead.
        'status_codes' => [
            'SH005' => 'delivered',
            'SH003' => 'out_for_delivery',
        ],

        'shipper' => [
            'name'         => env('ARAMEX_SHIPPER_NAME', 'Aroma'),
            'company'      => env('ARAMEX_SHIPPER_COMPANY', 'Aroma Gift Center'),
            'phone'        => env('ARAMEX_SHIPPER_PHONE'),
            'email'        => env('ARAMEX_SHIPPER_EMAIL'),
            'line1'        => env('ARAMEX_SHIPPER_ADDRESS_LINE1'),
            'line2'        => env('ARAMEX_SHIPPER_ADDRESS_LINE2'),
            'city'         => env('ARAMEX_SHIPPER_CITY', 'Riyadh'),
            'post_code'    => env('ARAMEX_SHIPPER_POST_CODE'),
            'country_code' => env('ARAMEX_SHIPPER_COUNTRY_CODE', 'SA'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tawked Verify API (phone-number OTP)
    |--------------------------------------------------------------------------
    | Tawked generates, delivers and checks the one-time code — this app never
    | sees or stores it. "tk_test_" keys are the sandbox (10 lifetime sends,
    | only to the account owner's verified phone); "tk_live_" is production.
    | `dev_code` is honoured ONLY when APP_ENV=local (see TawkedClient).
    */
    'tawked' => [
        'base_url' => env('TAWKED_BASE_URL', 'https://tawked.com'),
        'api_key'  => env('TAWKED_API_KEY'),
        'timeout'  => (int) env('TAWKED_TIMEOUT', 10),
        'dev_code' => env('TAWKED_DEV_CODE'),
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

    // "Sign in with Apple" — the UI button (auth/partials/social.blade.php)
    // only renders once client_id is actually set (see
    // SocialAuthController::configuredProviders()); real Apple sign-in also
    // needs a community Socialite provider (socialiteproviders/apple) plus a
    // Services ID/key/team ID from an Apple Developer account, none of which
    // exist yet — this block just gives the "is it configured" check
    // something to read once those arrive.
    'apple' => [
        'client_id'     => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect'      => env('APPLE_REDIRECT_URI'),
    ],

];
