<?php
return [
    'design'          => [
        'custom'            => false,
        'logo'              => null,
        'business_scope'    => '',
        'phone'             => '',
        'phone_hint'        => '',
        'address'           => '',
        'working_hours'     => '',
        'order_background'  => '#e3e3e3',
        'layout_background' => '#ffffff',
        'custom_css'        => '',
    ],
    'cart'            => [
        'block_name'       => _wd('shop', 'Shopping cart'),
        'empty_text'       => _wd('shop', 'Your shopping cart is empty'),
        'change_sku'       => false,
        'discount_item'    => shopCheckoutConfig::DISCOUNT_ITEM_TYPE_STRIKEOUT,
        'discount_general' => shopCheckoutConfig::DISCOUNT_GENERAL_TYPE_AMOUNT,
        'show_weight'      => true,
    ],
    'recommendations' => [
        'used'       => false,
        'block_name' => _wd('shop', 'Recommended'),
    ],
    'schedule'        => [
        'mode'            => shopCheckoutConfig::SCHEDULE_MODE_DEFAULT,
        'processing_time' => '0',
    ],
    'order'           => [
        'block_name' => _wd('shop', 'Checkout block'),
    ],
    'customer'        => [
        'block_name'             => _wd('shop', 'Customer'),
        'offer_login'            => _wd('shop',
            'Logging in makes ordering simple because all your orders are saved in your account history and you do not need to enter contact details for every new order.'),
        'offer_logout'           => _wd('shop', 'Log out if you want to check out as another customer'),
        'type'                   => shopCheckoutConfig::CUSTOMER_TYPE_PERSON,
        'fields_person'          => [],
        'fields_company'         => [],
        'person_mode_name'       => _wd('shop', 'Person'),
        'company_mode_name'      => _wd('shop', 'Company'),
        'company_hint'           => '',
        'company_terms'          => '',
        'service_agreement'      => shopCheckoutConfig::CUSTOMER_SERVICE_AGREEMENT_TYPE_NO,
        'service_agreement_hint' => '',
    ],
    'shipping'        => [
        'used'                   => true,
        'block_name'             => _wd('shop', 'Shipping'),
        'mode'                   => shopCheckoutConfig::SHIPPING_MODE_TYPE_DEFAULT,
        'fixed_delivery_area'    => [
            'country' => null,
            'region'  => null,
            'city'    => null,
        ],
        'show_pickuppoint_map'   => shopCheckoutConfig::PICKUPPOINT_MAP_TYPE_ALWAYS,
        'locations_list'         => [],
        'ask_zip'                => false,
        'courier_name'           => _wd('shop', 'Courier'),
        'pickuppoint_name'       => _wd('shop', 'Pickup'),
        'post_name'              => _wd('shop', 'Post'),
        'address_fields'         => [],
        'service_agreement'      => false,
        'service_agreement_hint' => _wd('shop', 'By placing an order you confirm that you are of adult age and agree to our <a href="">personal data processing terms</a>'),
        'plugin_timeout'         => 10, // In seconds
        'auto_use_timeout'       => 3,  // In seconds, if there is empty or <= 0 timeout will not be applied
    ],
    'payment'         => [
        'used'       => true,
        'block_name' => _wd('shop', 'Payment')
    ],
    'confirmation'    => [
        'auto_submit'        => true,
        'order_comment'      => false,
        'terms'              => false,
        'terms_text'         => '',
        'order_without_auth' => shopCheckoutConfig::ORDER_WITHOUT_AUTH_CREATE,
        'recode_timeout'     => 60, // In seconds
        'thankyou_header'    => _wd('shop', 'We have received your order. Thank you!'),
        'thankyou_content'   => _wd('shop', 'We will contact you soon. Your order No. is $order_id.'),
    ],
];
