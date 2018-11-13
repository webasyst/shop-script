<?php

return array(
    'design'       => array(
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
    ),
    'cart'         => array(
        'block_name'       => _w('Shopping cart'),
        'empty_text'       => _w('Your shopping cart is empty'),
        'article_change'   => false,
        'discount_item'    => shopCheckoutConfig::DISCOUNT_ITEM_TYPE_STRIKEOUT,
        'discount_general' => shopCheckoutConfig::DISCOUNT_GENERAL_TYPE_AMOUNT,
    ),
    'schedule' => array(
        'mode' => shopCheckoutConfig::SCHEDULE_MODE_DEFAULT,
        'processing_time' => '0',
    ),
    'order'        => array(
        'block_name'           => _w('Appearance'),
        'mode'                 => shopCheckoutConfig::ORDER_MODE_TYPE_DEFAULT,
        'fixed_delivery_area'  => array(
            'country' => null,
            'region'  => null,
            'city'    => null,
        ),
        'show_pickuppoint_map' => shopCheckoutConfig::PICKUPPOINT_MAP_TYPE_ALWAYS,
        'locations_list'       => array(),
    ),
    'customer'     => array(
        'block_name'             => _w('Customer'),
        'offer_login'            => _w('Logging in makes ordering simple because all your orders are saved in your account history and you do not need to enter contact details for every new order.'),
        'offer_logout'           => _w('Log out if you want to check out as another customer'),
        'type'                   => shopCheckoutConfig::CUSTOMER_TYPE_PERSON,
        'fields_person'          => array(),
        'fields_company'         => array(),
        'person_mode_name'       => _w('Persons'),
        'company_mode_name'      => _w('Companies'),
        'company_hint'           => '',
        'company_terms'          => '',
        'service_agreement'      => shopCheckoutConfig::CUSTOMER_SERVICE_AGREEMENT_TYPE_NO,
        'service_agreement_hint' => '',
    ),
    'shipping'     => array(
        'used'                   => false,
        'block_name'             => _w('Shipping'),
        'ask_zip'                => false,
        'courier_name'           => _w('Courier'),
        'pickuppoint_name'       => _w('Pickup'),
        'post_name'              => _w('Post'),
        'address_fields'         => array(),
        'service_agreement'      => false,
        'service_agreement_hint' => _w('By placing an order you confirm that you are of adult age and agree to our <a href="">personal data processing terms</a>'),
        'plugin_timeout'         => 10, // In seconds
    ),
    'payment'      => array(
        'used'       => false,
        'block_name' => _w('Payment')
    ),
    'confirmation' => array(
        'order_comment'      => false,
        'terms'              => false,
        'terms_text'         => '',
        'order_without_auth' => shopCheckoutConfig::ORDER_WITHOUT_AUTH_CREATE,
        'auth_with_code'     => true,
        'recode_timeout'     => 60, // In seconds
        'thankyou_header'    => _w('We have received your order. Thank you!'),
        'thankyou_content'   => _w('We will contact you soon. Your order No. is $order_id.'),
    ),
);