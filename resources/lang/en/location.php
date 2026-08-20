<?php

return [
    'label' => 'Saudi National Address Code',
    'placeholder' => 'RAHA1234',
    'hint' => 'Find your code on the Saudi National Address app or your Saudi Post mail.',

    'preview' => [
        'title' => 'Resolved address',
        'city' => 'City',
        'region' => 'Region',
        'district' => 'District',
        'address' => 'Address',
        'loading' => 'Looking up your address…',
    ],

    'errors' => [
        'invalid_format' => 'Enter a valid code: 4 letters followed by 4 digits (e.g. RAHA1234).',
        'not_found' => 'We couldn\'t find this location code. Please check it and try again.',
        'lookup_failed' => 'We couldn\'t look up this address right now. Please try again.',
    ],
];
