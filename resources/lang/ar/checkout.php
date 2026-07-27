<?php

return [
    'title' => 'الدفع',
    'step_of' => 'الخطوة :n من :total — :label',
    'review' => 'مراجعة الطلب',
    'address' => 'عنوان الشحن',
    'payment' => 'طريقة الدفع',
    'confirmation' => 'تأكيد الطلب',

    'steps' => [
        'review' => 'السلة',
        'account' => 'الحساب',
        'address' => 'العنوان',
        'gift' => 'الهدية',
        'delivery' => 'التوصيل',
        'order_review' => 'المراجعة',
        'payment' => 'الدفع',
    ],

    'auth' => [
        'title' => 'كيف تود إتمام الطلب؟',
        'subtitle' => 'سجّل الدخول لإتمام الطلب بشكل أسرع، أو تابع كضيف.',
        'login_title' => 'تسجيل الدخول',
        'login_desc' => 'هل لديك حساب في أروما؟ سجّل الدخول لاستخدام بياناتك المحفوظة.',
        'register_title' => 'إنشاء حساب',
        'register_desc' => 'جديد على أروما؟ أنشئ حساباً لتتبع طلباتك وإتمام الشراء بشكل أسرع في المرة القادمة.',
        'continue_as_guest' => 'المتابعة كضيف',
        'benefit_tracking' => 'تتبع طلباتك',
        'benefit_faster' => 'شراء أسرع في المرة القادمة',
        'benefit_addresses' => 'احفظ عناوينك',
    ],

    'recipient_name' => 'الاسم الكامل',
    'phone' => 'رقم الهاتف',
    'street_address' => 'عنوان الشارع',
    'city' => 'المدينة',
    'region' => 'المنطقة',
    'postal_code' => 'الرمز البريدي',
    'email' => 'عنوان البريد الإلكتروني',

    'use_shipping_for_billing' => 'استخدام عنوان الشحن للفواتير',
    'customer_notes' => 'تعليمات خاصة (اختياري)',
    'coupon_code' => 'رمز القسيمة',
    'shipping_method' => 'طريقة الشحن',

    'payment_gateway' => 'بوابة الدفع',
    'payment_method' => 'طريقة الدفع',

    'subtotal' => 'المجموع الفرعي',
    'discount' => 'الخصم',
    'tax' => 'الضريبة',
    'shipping' => 'الشحن',
    'total' => 'الإجمالي',
    'total_amount' => 'المبلغ الإجمالي',
    'product' => 'المنتج',
    'quantity' => 'الكمية',
    'price' => 'السعر',
    'review_order' => 'مراجعة طلبك',
    'back_to_cart' => 'العودة إلى السلة',
    'proceed_to_checkout' => 'المتابعة إلى الدفع',
    'secure_checkout' => 'دفع آمن — معلوماتك محمية',
    'coming_soon' => 'قريباً',

    'buttons' => [
        'continue' => 'متابعة',
        'back' => 'رجوع',
        'place_order' => 'تأكيد الطلب',
        'confirm_order' => 'تأكيد الطلب',
    ],

    'success' => [
        'order_created' => 'تم إنشاء الطلب :order_number بنجاح!',
        'payment_confirmed' => 'تم تأكيد الدفع.',
    ],

    'errors' => [
        'cart_empty' => 'سلتك فارغة.',
        'product_out_of_stock' => 'المنتج ":name" غير متوفر حالياً.',
        'insufficient_stock' => 'المخزون غير كافي لهذا المنتج.',
        'payment_failed' => 'فشل معالجة الدفع. يرجى المحاولة مرة أخرى.',
        'invalid_address' => 'يرجى تقديم عنوان شحن صحيح.',
        'bnpl_unavailable' => ':gateway غير متاح لهذا الطلب. يرجى اختيار وسيلة دفع أخرى.',
    ],

    'payment_methods' => [
        'mada' => 'بطاقة مدى',
        'applepay' => 'Apple Pay',
        'visa' => 'بطاقة Visa',
        'mastercard' => 'Mastercard',
        'tabby' => 'تابي (اشتر الآن ادفع لاحقاً)',
        'tamara' => 'تمارا (اشتر الآن ادفع لاحقاً)',
    ],

    'order_number' => 'رقم الطلب',
    'order_date' => 'تاريخ الطلب',
    'status' => 'الحالة',
    'email_confirmation' => 'تم إرسال بريد تأكيد إلى عنوان بريدك الإلكتروني.',
    'thank_you' => 'شكراً لك!',
    'order_confirmed' => 'تم تأكيد طلبك وجاري معالجته.',
    'shipping_address_label' => 'عنوان الشحن',
    'order_items' => 'عناصر الطلب',
    'order_summary' => 'ملخص الطلب',
    'view_order' => 'عرض الطلب',
    'continue_shopping' => 'متابعة التسوق',
    'back' => 'رجوع',
    'agree_terms' => 'أوافق على <a href=":link">الشروط والأحكام</a>',
    'placeholder' => 'نموذج الدفع لـ :gateway سيتم عرضه هنا',
    'payment_gateway_placeholder' => 'نموذج الدفع',
    'shipping_methods' => [
        'standard' => 'الشحن العادي',
    ],
    'shipping_days' => [
        'standard' => '3-5 أيام عمل',
    ],
    'saved_addresses' => 'العناوين المحفوظة',

    'order_review' => [
        'title' => 'مراجعة طلبك',
        'items_title' => 'العناصر',
        'delivering_to' => 'التوصيل إلى',
        'not_a_gift' => 'هذا الطلب ليس هدية.',
        'anonymous_badge' => 'مجهول',
        'edit' => 'تعديل',
        'continue_to_payment' => 'المتابعة إلى الدفع',
    ],
];
