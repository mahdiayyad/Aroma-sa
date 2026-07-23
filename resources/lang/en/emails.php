<?php

return [
    'order_confirmation' => [
        'subject' => 'Order Confirmation - :order_number',
        'title' => 'Order Confirmation',
        'greeting' => 'Thank you for your order, :name!',
        'message' => 'Your order has been confirmed and is being processed. Below are the details of your purchase.',
        'order_details' => 'Order Details',
        'shipping_address' => 'Shipping Address',
        'view_order' => 'View Order',
        'thank_you' => 'Thank you for shopping with us!',
    ],

    'order_shipped' => [
        'subject' => 'Your Order Has Been Shipped - :order_number',
        'title' => 'Order Shipped',
        'greeting' => 'Great news!',
        'status' => 'Your Order Has Been Shipped',
        'message' => 'Your order is on its way! Track your package using the information below.',
        'shipping_address' => 'Shipping Address',
        'tracking_info' => 'You can track your shipment using the tracking number above. Updates will be sent to you as your package moves.',
        'view_order' => 'Track Your Order',
        'thank_you' => 'We appreciate your business!',
    ],

    'payment_failed' => [
        'subject' => 'Payment Failed - Order :order_number',
        'title' => 'Payment Failed',
        'greeting' => 'Payment Issue',
        'status' => 'Payment Processing Failed',
        'message' => 'Unfortunately, your payment could not be processed.',
        'reason' => 'This may be due to insufficient funds, incorrect card details, or a temporary issue with your bank. Your order is still reserved for you.',
        'order_details' => 'Order Details:',
        'retry' => 'Retry Payment',
        'retry_message' => 'You can retry the payment using the button below. Your order will be confirmed once the payment is successful.',
        'retry_payment' => 'Try Payment Again',
        'support' => 'If you continue to have issues, please contact our support team.',
    ],

    'footer' => [
        'contact' => 'Contact Us',
        'whatsapp' => 'WhatsApp',
        'all_rights' => 'All rights reserved.',
    ],
];
