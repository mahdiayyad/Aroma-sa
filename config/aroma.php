<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Aroma Brand & Store Configuration
|--------------------------------------------------------------------------
|
| Single source of truth for brand identity and store-wide settings. Values
| here are derived from the official Aroma Brand Guidelines (colour palette,
| typography, tagline) and from the Saudi market requirements agreed for the
| project. Anything visual should reference these tokens rather than hard-code
| hex values or font names in Blade/CSS.
|
*/

return [

    /*
    | Brand identity ---------------------------------------------------------
    */
    'brand' => [
        'name'    => 'Aroma',
        'tagline' => 'Awaken your Senses',
        'archetype' => 'The Lover', // per brand guidelines — informs copy tone
    ],

    /*
    | Colour palette (Brand Guidelines p.14) ---------------------------------
    | Note: the PDF prints the warm white as "#FF3E2" which is a typo for the
    | intended #FFF3E2 (R255 G243 B226). We use the corrected value.
    */
    'colors' => [
        'brown'       => '#704F2F',
        'light_brown' => '#D3A17A',
        'skin'        => '#FFE5CA',
        'white'       => '#FFF3E2',
        'ink'         => '#2B2016', // readable dark text on warm backgrounds
    ],

    /*
    | Typography (Brand Guidelines pp.15-19) ---------------------------------
    | The licensed brand fonts (Manier, Snell Roundhand, Luxury, Helvetica Neue
    | LT Arabic) are proprietary and must be dropped into public/fonts. Until
    | then the CSS falls back to the stacks defined below.
    */
    'fonts' => [
        // Luxury is the primary display/heading typeface for English.
        // Tajwal is an elegant modern Arabic typeface (Google Fonts).
        'heading_en' => 'Luxury',
        'heading_en_fallback' => 'Manier',
        'body_en'    => 'Helvetica Neue',
        'script'     => 'Snell Roundhand', // tagline accent only
        'heading_ar' => 'Tajwal',
        'body_ar'    => 'Tajwal',
    ],

    /*
    | Localisation -----------------------------------------------------------
    | Bilingual store (decision: launch AR + EN with RTL from day one).
    */
    'locales' => [
        'ar' => ['name' => 'العربية', 'dir' => 'rtl', 'native' => 'العربية'],
        'en' => ['name' => 'English', 'dir' => 'ltr', 'native' => 'English'],
    ],
    'default_locale' => 'ar',

    /*
    | Commerce ---------------------------------------------------------------
    */
    'currency' => [
        'code'     => 'SAR',
        'symbol'   => 'ر.س',
        'decimals' => 2,
    ],
    'country'  => 'SA',

    /*
    | Payment methods surfaced at checkout (trust badges / gateway routing) ---
    */
    'payments' => [
        'primary_gateway' => 'moyasar',
        'methods'         => ['mada', 'applepay', 'visa', 'mastercard', 'tamara'],
        'bnpl'            => ['tamara'],
    ],

    /*
    | Gifting (checkout Gift Options step) -------------------------------------
    */
    'gifting' => [
        'wrap_fee'          => (float) env('AROMA_GIFT_WRAP_FEE', 15.00),
        'message_max_chars' => 200,
        'message_max_lines' => 5,
    ],

    // Used only when a live Aramex rate call fails or the account isn't
    // configured — checkout must never hard-block on a carrier outage.
    // These are business decisions, not invented values: confirm real
    // figures before deploy, 0.0 means an outage ships for free.
    'shipping' => [
        'domestic_flat_rate'      => (float) env('AROMA_SHIPPING_FLAT_RATE_DOMESTIC', 0.0),
        'international_flat_rate' => (float) env('AROMA_SHIPPING_FLAT_RATE_INTERNATIONAL', 0.0),
    ],

    /*
    | Payment receipts ---------------------------------------------------------
    | PDFs hold customer names, addresses and payment details: keep them on a
    | private disk (never "public"); they're served through authorised routes.
    */
    'receipts' => [
        'disk' => env('AROMA_RECEIPTS_DISK', 'local'),
        // Shown under the merchant name, e.g. "Omar Bin Khattab St., Dammam".
        'store_address' => env('AROMA_STORE_ADDRESS', ''),
        'vat_number' => env('AROMA_VAT_NUMBER', ''),
    ],

    /*
    | Contact / footer -------------------------------------------------------
    */
    'contact' => [
        // The public-facing contact address (footer, Contact page, policies).
        // Deliberately separate from MAIL_FROM_ADDRESS, which is the
        // technical "sent from" address for system emails — the two don't
        // have to be the same mailbox.
        'email'    => env('AROMA_CONTACT_EMAIL', 'info@aromagiftcenter.com'),
        'phone'    => env('AROMA_PHONE', ''),
        // Digits only, incl. country code — e.g. 9665XXXXXXXX. The floating
        // WhatsApp button only appears once this is set.
        'whatsapp' => env('AROMA_WHATSAPP', ''),
        // Full profile URLs. Each footer icon stays hidden until its URL is
        // set — see layouts/partials/footer.blade.php.
        'instagram' => env('AROMA_INSTAGRAM', ''),
        'tiktok' => env('AROMA_TIKTOK', ''),
        'snapchat' => env('AROMA_SNAPCHAT', ''),
        'facebook' => env('AROMA_FACEBOOK', ''),
        // Shown in the footer once set, next to receipts.vat_number.
        'freelance_document' => env('AROMA_FREELANCE_DOCUMENT', ''),
    ],

    /*
    | Ad pixels --------------------------------------------------------------
    | Public IDs (they appear in the page source). Blank one in .env to turn
    | that pixel off. See App\Support\MarketingPixels for when they load.
    */
    'marketing' => [
        'meta_pixel_id' => env('META_PIXEL_ID', '1127879479647421'),
        'tiktok_pixel_id' => env('TIKTOK_PIXEL_ID', 'DBO7FMBC77UBQ4PDOUTG'),
        // Optional. When set (GTM-XXXXXXX) the container loads and receives the
        // dataLayer e-commerce events. Meta/TikTok events are sent by the site
        // itself (public/js/aroma-tracking.js) — don't add Meta/TikTok tags for
        // the same events in GTM, or they'll be counted twice.
        'gtm_container_id' => env('GTM_CONTAINER_ID'),
    ],

    /*
    | Referral & reward points -----------------------------------------------
    | The single source of truth for the points<->SAR conversion and referral
    | bonus amounts — RewardPointService/ReferralService read this rather
    | than hardcoding the numbers, so the economics can change without
    | touching application code.
    */
    'rewards' => [
        'points_per_sar' => (int) env('AROMA_POINTS_PER_SAR', 10),
        'referral_signup_bonus' => (int) env('AROMA_REFERRAL_SIGNUP_BONUS', 100), // the new (referred) customer
        'referral_reward' => (int) env('AROMA_REFERRAL_REWARD', 100), // the referrer
    ],

    /*
    | Admin abilities -----------------------------------------------------------
    | Which roles may do what in the back-office. Registered as Gates in
    | AuthServiceProvider (Gate::allows('products.import')), enforced by `can:`
    | route middleware and hidden in the UI with @can. The role list is the
    | only thing to edit to change who can import/export.
    */
    'admin' => [
        'abilities' => [
            'products.export'         => ['admin', 'staff'],
            'products.template'       => ['admin', 'staff'],
            'products.import'         => ['admin'],
            'products.import.history' => ['admin'],
        ],
    ],

    /*
    | Product import / export (see docs/product-import-export) ------------------
    */
    'import' => [
        'max_upload_mb'      => (int) env('AROMA_IMPORT_MAX_UPLOAD_MB', 10),
        // Files at or under BOTH thresholds run inline in the request (instant
        // results, no queue worker needed); anything bigger goes to the queue.
        'sync_max_rows'      => (int) env('AROMA_IMPORT_SYNC_MAX_ROWS', 100),
        'sync_max_images'    => (int) env('AROMA_IMPORT_SYNC_MAX_IMAGES', 25),
        'apply_sync_max_rows' => (int) env('AROMA_IMPORT_APPLY_SYNC_MAX_ROWS', 500), // apply is database-only, so it can go bigger inline
        'queue'              => env('AROMA_IMPORT_QUEUE', 'imports'),
        'chunk_size'         => 250,
        'error_cap'          => 1000,   // errors kept on the run; the full list is in the downloadable report
        'retention_days'     => 30,
        'max_images_per_product' => 10,
        'image_max_mb'       => 5,
        'image_timeout'      => 15,     // seconds per download
        'image_max_redirects' => 3,
        'image_mimes'        => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ],
];
