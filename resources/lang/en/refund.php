<?php

return [
    'title' => 'Request a Refund',
    'subtitle' => 'Tell us what happened and we\'ll review your order.',
    'no_eligible_orders' => 'You have no paid orders eligible for a refund request yet.',

    'form' => [
        'order' => 'Order',
        'order_placeholder' => 'Select an order',
        'reason' => 'Reason',
        'reason_placeholder' => 'Select a reason',
        'notes' => 'Additional details (optional)',
        'submit' => 'Submit request',
        'success' => 'Your refund request has been submitted. We\'ll be in touch soon.',
    ],

    'reasons' => [
        'damaged' => 'Item arrived damaged',
        'wrong_item' => 'Received the wrong item',
        'not_as_described' => 'Item not as described',
        'no_longer_needed' => 'No longer needed',
        'other' => 'Other',
    ],

    'status' => [
        'pending' => 'Pending review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],

    'errors' => [
        'order_not_paid' => 'Refunds can only be requested for a paid order.',
        'already_pending' => 'You already have a pending refund request for this order.',
    ],
];
