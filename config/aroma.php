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
        'methods'         => ['mada', 'applepay', 'visa', 'mastercard', 'tabby', 'tamara'],
        'bnpl'            => ['tabby', 'tamara'],
    ],

    /*
    | Gifting (checkout Gift Options step) -------------------------------------
    */
    'gifting' => [
        'wrap_fee'          => (float) env('AROMA_GIFT_WRAP_FEE', 15.00),
        'message_max_chars' => 200,
        'message_max_lines' => 5,
    ],

    /*
    | Delivery scheduling (checkout) ------------------------------------------
    | Data capture only for v1 — no carrier/slot-capacity logic yet (Aramex
    | integration is a separate, later project). The shopper picks a date at
    | least `min_lead_days` out and one of the fixed time windows below; staff
    | fulfil manually via the admin order view.
    */
    'delivery' => [
        'min_lead_days'  => (int) env('AROMA_DELIVERY_MIN_LEAD_DAYS', 1),
        'max_lead_days'  => (int) env('AROMA_DELIVERY_MAX_LEAD_DAYS', 30),
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
        'instagram' => 'aroma',
    ],
];
