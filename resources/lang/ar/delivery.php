<?php

return [
    'title' => 'متى نوصّل الطلب؟',
    'date_label' => 'تاريخ التوصيل',
    'slot_label' => 'الوقت المفضل',
    'instructions_label' => 'تعليمات التوصيل (اختياري)',
    'instructions_placeholder' => 'رمز البوابة، علامة مميزة، طريقة التواصل المفضلة…',

    'slots' => [
        'morning'   => 'صباحاً',
        'afternoon' => 'ظهراً',
        'evening'   => 'مساءً',
    ],
    'slot_times' => [
        'morning'   => '9 ص – 12 ظ',
        'afternoon' => '12 ظ – 4 م',
        'evening'   => '4 م – 8 م',
    ],

    'errors' => [
        'date_required' => 'يرجى اختيار تاريخ التوصيل.',
        'date_range' => 'يرجى اختيار تاريخ بين :min و :max.',
        'slot_required' => 'يرجى اختيار الوقت المفضل للتوصيل.',
    ],
];
