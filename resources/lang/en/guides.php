<?php

// Chrome strings shared across the 4 Customer Guide detail pages (sub-nav) and
// the product-page guide-links partial. Page prose itself stays inline in each
// pages/guides/*.blade.php file — see privacy-policy.blade.php / about.blade.php
// for why this codebase keeps long-form content out of lang files.

return [
    'nav' => [
        'sizing'  => 'Size Guide',
        'fit'     => 'Find Your Fit',
        'care'    => 'Care & Washing',
        'returns' => 'Exchange & Returns',
    ],

    'product_links' => [
        'title'   => 'Shopping this abaya?',
        'sizing'  => 'Size Guide',
        'fit'     => 'Find Your Fit',
        'care'    => 'Care Instructions',
    ],
];
