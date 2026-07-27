<?php

return [
    'title' => 'When should we deliver?',
    'date_label' => 'Delivery date',
    'slot_label' => 'Preferred time',
    'instructions_label' => 'Delivery instructions (optional)',
    'instructions_placeholder' => 'Gate code, landmark, preferred contact method…',

    'slots' => [
        'morning'   => 'Morning',
        'afternoon' => 'Afternoon',
        'evening'   => 'Evening',
    ],
    'slot_times' => [
        'morning'   => '9 AM – 12 PM',
        'afternoon' => '12 PM – 4 PM',
        'evening'   => '4 PM – 8 PM',
    ],

    'errors' => [
        'date_required' => 'Please choose a delivery date.',
        'date_range' => 'Please choose a date between :min and :max.',
        'slot_required' => 'Please choose a preferred delivery time.',
    ],
];
