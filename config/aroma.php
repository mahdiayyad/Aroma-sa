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
        // Luxury is the primary display/heading typeface for both locales.
        'heading_en' => 'Luxury',
        'heading_en_fallback' => 'Manier',
        'body_en'    => 'Helvetica Neue',
        'script'     => 'Snell Roundhand', // tagline accent only
        'heading_ar' => 'Luxury',
        'body_ar'    => 'Helvetica Neue LT Arabic',
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
    | Contact / footer -------------------------------------------------------
    */
    'contact' => [
        'email'    => env('MAIL_FROM_ADDRESS', 'hello@aroma.sa'),
        'phone'    => '+966',
        'whatsapp' => '+966',
        'instagram' => 'aroma',
    ],
];
