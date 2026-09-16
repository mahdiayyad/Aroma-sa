<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'The :attribute must be accepted.',
    'accepted_if' => 'The :attribute must be accepted when :other is :value.',
    'active_url' => 'The :attribute is not a valid URL.',
    'after' => 'The :attribute must be a date after :date.',
    'after_or_equal' => 'The :attribute must be a date after or equal to :date.',
    'alpha' => 'The :attribute must only contain letters.',
    'alpha_dash' => 'The :attribute must only contain letters, numbers, dashes and underscores.',
    'alpha_num' => 'The :attribute must only contain letters and numbers.',
    'array' => 'The :attribute must be an array.',
    'before' => 'The :attribute must be a date before :date.',
    'before_or_equal' => 'The :attribute must be a date before or equal to :date.',
    'between' => [
        'numeric' => 'The :attribute must be between :min and :max.',
        'file' => 'The :attribute must be between :min and :max kilobytes.',
        'string' => 'The :attribute must be between :min and :max characters.',
        'array' => 'The :attribute must have between :min and :max items.',
    ],
    'boolean' => 'The :attribute field must be true or false.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'current_password' => 'The password is incorrect.',
    'date' => 'The :attribute is not a valid date.',
    'date_equals' => 'The :attribute must be a date equal to :date.',
    'date_format' => 'The :attribute does not match the format :format.',
    'declined' => 'The :attribute must be declined.',
    'declined_if' => 'The :attribute must be declined when :other is :value.',
    'different' => 'The :attribute and :other must be different.',
    'digits' => 'The :attribute must be :digits digits.',
    'digits_between' => 'The :attribute must be between :min and :max digits.',
    'dimensions' => 'The :attribute has invalid image dimensions.',
    'distinct' => 'The :attribute field has a duplicate value.',
    'email' => 'The :attribute must be a valid email address.',
    'ends_with' => 'The :attribute must end with one of the following: :values.',
    'enum' => 'The selected :attribute is invalid.',
    'exists' => 'The selected :attribute is invalid.',
    'file' => 'The :attribute must be a file.',
    'filled' => 'The :attribute field must have a value.',
    'gt' => [
        'numeric' => 'The :attribute must be greater than :value.',
        'file' => 'The :attribute must be greater than :value kilobytes.',
        'string' => 'The :attribute must be greater than :value characters.',
        'array' => 'The :attribute must have more than :value items.',
    ],
    'gte' => [
        'numeric' => 'The :attribute must be greater than or equal to :value.',
        'file' => 'The :attribute must be greater than or equal to :value kilobytes.',
        'string' => 'The :attribute must be greater than or equal to :value characters.',
        'array' => 'The :attribute must have :value items or more.',
    ],
    'image' => 'The :attribute must be an image.',
    'in' => 'The selected :attribute is invalid.',
    'in_array' => 'The :attribute field does not exist in :other.',
    'integer' => 'The :attribute must be an integer.',
    'ip' => 'The :attribute must be a valid IP address.',
    'ipv4' => 'The :attribute must be a valid IPv4 address.',
    'ipv6' => 'The :attribute must be a valid IPv6 address.',
    'json' => 'The :attribute must be a valid JSON string.',
    'lt' => [
        'numeric' => 'The :attribute must be less than :value.',
        'file' => 'The :attribute must be less than :value kilobytes.',
        'string' => 'The :attribute must be less than :value characters.',
        'array' => 'The :attribute must have less than :value items.',
    ],
    'lte' => [
        'numeric' => 'The :attribute must be less than or equal to :value.',
        'file' => 'The :attribute must be less than or equal to :value kilobytes.',
        'string' => 'The :attribute must be less than or equal to :value characters.',
        'array' => 'The :attribute must not have more than :value items.',
    ],
    'mac_address' => 'The :attribute must be a valid MAC address.',
    'max' => [
        'numeric' => 'The :attribute must not be greater than :max.',
        'file' => 'The :attribute must not be greater than :max kilobytes.',
        'string' => 'The :attribute must not be greater than :max characters.',
        'array' => 'The :attribute must not have more than :max items.',
    ],
    'mimes' => 'The :attribute must be a file of type: :values.',
    'mimetypes' => 'The :attribute must be a file of type: :values.',
    'min' => [
        'numeric' => 'The :attribute must be at least :min.',
        'file' => 'The :attribute must be at least :min kilobytes.',
        'string' => 'The :attribute must be at least :min characters.',
        'array' => 'The :attribute must have at least :min items.',
    ],
    'multiple_of' => 'The :attribute must be a multiple of :value.',
    'not_in' => 'The selected :attribute is invalid.',
    'not_regex' => 'The :attribute format is invalid.',
    'numeric' => 'The :attribute must be a number.',
    'password' => 'The password is incorrect.',
    'present' => 'The :attribute field must be present.',
    'prohibited' => 'The :attribute field is prohibited.',
    'prohibited_if' => 'The :attribute field is prohibited when :other is :value.',
    'prohibited_unless' => 'The :attribute field is prohibited unless :other is in :values.',
    'prohibits' => 'The :attribute field prohibits :other from being present.',
    'regex' => 'The :attribute format is invalid.',
    'required' => 'The :attribute field is required.',
    'required_array_keys' => 'The :attribute field must contain entries for: :values.',
    'required_if' => 'The :attribute field is required when :other is :value.',
    'required_unless' => 'The :attribute field is required unless :other is in :values.',
    'required_with' => 'The :attribute field is required when :values is present.',
    'required_with_all' => 'The :attribute field is required when :values are present.',
    'required_without' => 'The :attribute field is required when :values is not present.',
    'required_without_all' => 'The :attribute field is required when none of :values are present.',
    'same' => 'The :attribute and :other must match.',
    'size' => [
        'numeric' => 'The :attribute must be :size.',
        'file' => 'The :attribute must be :size kilobytes.',
        'string' => 'The :attribute must be :size characters.',
        'array' => 'The :attribute must contain :size items.',
    ],
    'starts_with' => 'The :attribute must start with one of the following: :values.',
    'string' => 'The :attribute must be a string.',
    'timezone' => 'The :attribute must be a valid timezone.',
    'unique' => 'The :attribute has already been taken.',
    'uploaded' => 'The :attribute failed to upload.',
    'url' => 'The :attribute must be a valid URL.',
    'uuid' => 'The :attribute must be a valid UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        // Generic / shared across several forms
        'name'                   => 'name',
        'email'                  => 'email address',
        'phone'                  => 'mobile number',
        'password'               => 'password',
        'password_confirmation'  => 'password confirmation',
        'gender'                 => 'gender',
        'dob'                    => 'date of birth',
        'locale'                 => 'language',
        'login'                  => 'email or mobile number',
        'remember'               => 'remember me',

        // Account address book (account/addresses — plain field names)
        'label'                  => 'label',
        'recipient_name'         => 'recipient name',
        'street_address'         => 'street address',
        'city'                   => 'city',
        'region'                 => 'region',
        'postal_code'            => 'postal code',
        'latitude'               => 'latitude',
        'longitude'              => 'longitude',
        'is_default'             => 'default address',

        // Checkout — billing address
        'billing_address.recipient_name' => 'recipient name',
        'billing_address.email'          => 'email address',
        'billing_address.phone'          => 'mobile number',
        'billing_address.street_address' => 'street address',
        'billing_address.city'           => 'city',
        'billing_address.region'         => 'region',
        'billing_address.postal_code'    => 'postal code',
        'billing_address.latitude'       => 'latitude',
        'billing_address.longitude'      => 'longitude',

        // Checkout — separate shipping address
        'use_shipping_for_billing'        => 'use billing address for shipping',
        'shipping_address.recipient_name' => 'shipping recipient name',
        'shipping_address.phone'          => 'shipping recipient phone',
        'shipping_address.street_address' => 'shipping street address',
        'shipping_address.city'           => 'shipping city',
        'shipping_address.region'         => 'shipping region',
        'shipping_address.postal_code'    => 'shipping postal code',
        'shipping_address.latitude'       => 'shipping latitude',
        'shipping_address.longitude'      => 'shipping longitude',
        'customer_notes'                  => 'order notes',

        // Checkout — gift options / recipient
        'is_gift'                   => 'gift option',
        'recipient.recipient_name'  => 'recipient name',
        'recipient.phone'           => 'recipient phone',
        'recipient.street_address'  => 'recipient address',
        'recipient.city'            => 'recipient city',
        'recipient.region'          => 'recipient region',
        'recipient.postal_code'     => 'recipient postal code',
        'is_anonymous'              => 'sending anonymously',
        'gift_wrap'                 => 'gift wrap',
        'greeting_card_id'          => 'greeting card',
        'gift_to'                   => "recipient's name",
        'gift_from'                 => "sender's name",
        'gift_message'              => 'gift message',
        'gift_media_url'            => 'media link',
        'gift_signature_data'       => 'signature',

        // Checkout — payment
        'gateway'                => 'payment gateway',
        'method'                 => 'payment method',
        'coupon_code'            => 'coupon code',
        'shipping_method'        => 'shipping method',

        // Contact form
        'topic'                  => 'topic',
        'message'                => 'message',
        'website'                => 'website',

        // Admin — shared bilingual/meta fields
        'name.ar'                => 'name (Arabic)',
        'name.en'                => 'name (English)',
        'slug'                   => 'slug',
        'description.ar'         => 'description (Arabic)',
        'description.en'         => 'description (English)',
        'short_description.ar'   => 'short description (Arabic)',
        'short_description.en'   => 'short description (English)',
        'meta_title.ar'          => 'SEO title (Arabic)',
        'meta_title.en'          => 'SEO title (English)',
        'meta_description.ar'    => 'SEO description (Arabic)',
        'meta_description.en'    => 'SEO description (English)',
        'logo'                   => 'logo',
        'image'                  => 'image',
        'images'                 => 'images',
        'images.*'               => 'image',
        'icon'                   => 'icon',
        'parent_id'              => 'parent category',
        'sort_order'             => 'sort order',
        'is_active'              => 'active',
        'is_featured'            => 'featured',
        'is_new_arrival'         => 'new arrival',
        'is_gift_eligible'       => 'gift eligible',
        'has_variants'           => 'has variants',

        // Admin — products / categories / brands / gift cards / orders
        'category_id'            => 'category',
        'brand_id'                => 'brand',
        'sku'                    => 'SKU',
        'base_price'              => 'base price',
        'compare_at_price'         => 'compare-at price',
        'currency'                 => 'currency',
        'stock_quantity'           => 'stock quantity',
        'scent_family'             => 'scent family',
        'status'                   => 'status',
        'tracking_number'          => 'tracking number',
        'device'                   => 'device',
    ],

];
