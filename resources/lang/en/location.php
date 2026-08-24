<?php

return [
    'label' => 'Saudi National Address Code',
    'placeholder' => 'RAHA1234',
    'hint' => 'Find your code on the Saudi National Address app or your Saudi Post mail.',

    'method' => [
        'code' => 'Location code',
        'map' => 'Pin my location',
    ],

    'map' => [
        'label' => 'Pin your location',
        'hint' => 'Drag the pin, search, or use your current location.',
        'search_placeholder' => 'Search for a place…',
        'use_current' => 'Use my current location',
        'scroll_hint' => 'Click or tap the map to enable scroll-zoom',
        'detected_near' => 'Near: :place',
        'no_pin_yet' => 'Tap the map to drop a pin.',
        'pinned_label' => 'Pinned location',
        'search_loading' => 'Searching…',
        'search_no_results' => 'No matches found for ":query". Try a different spelling or search term.',
        'search_error' => 'We couldn\'t search right now. Please check your connection and try again.',
        'search_rate_limited' => 'Too many searches at once — please wait a moment and try again.',
        'search_retry' => 'Retry',
    ],

    'preview' => [
        'title' => 'Resolved address',
        'city' => 'City',
        'region' => 'Region',
        'district' => 'District',
        'address' => 'Address',
        'loading' => 'Looking up your address…',
        'demo_badge' => 'Demo data',
    ],

    'errors' => [
        'invalid_format' => 'Enter a valid code: 4 letters followed by 4 digits (e.g. RAHA1234).',
        'not_found' => 'We couldn\'t find this location code. Please check it and try again.',
        'lookup_failed' => 'We couldn\'t look up this address right now. Please try again.',
        'required_one' => 'Please enter a location code or pin your location on the map.',
    ],
];
