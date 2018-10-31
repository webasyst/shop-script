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
        'block_name'       => _w('Корзина'),
        'empty_text'       => _w('В вашей корзине пусто'),
        'article_change'   => false,
        'discount_item'    => shopCheckoutConfig::DISCOUNT_ITEM_TYPE_STRIKEOUT,
        'discount_general' => shopCheckoutConfig::DISCOUNT_GENERAL_TYPE_AMOUNT,
    ),
    'schedule' => array(
        'mode' => shopCheckoutConfig::SCHEDULE_MODE_DEFAULT,
        'processing_time' => '0',
    ),
    'order'        => array(
        'block_name'           => _w('Оформление'),
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
        'block_name'             => _w('Покупатель'),
        'offer_login'            => _w('Вход упрощает работу с магазином: все заказы сохраняются в истории личного кабинета, а при повторных заказах не нужно заполнять поля покупателя'),
        'offer_logout'           => _w('Выйдите, если вы хотите оформить заказ от имени другого пользователя'),
        'type'                   => shopCheckoutConfig::CUSTOMER_TYPE_PERSON,
        'fields_person'          => array(),
        'fields_company'         => array(),
        'person_mode_name'       => _w('Физ. лица'),
        'company_mode_name'      => _w('Юр. лица'),
        'company_hint'           => _w('Выйдите, если вы хотите оформить заказ от имени другого пользователя'),
        'company_terms'          => _w('Принимаю <a href="">условия работы</a>'),
        'service_agreement'      => shopCheckoutConfig::CUSTOMER_SERVICE_AGREEMENT_TYPE_NO,
        'service_agreement_hint' => '',
    ),
    'shipping'     => array(
        'used'                   => false,
        'block_name'             => _w('Доставка'),
        'ask_zip'                => false,
        'courier_name'           => _w('Курьер'),
        'pickuppoint_name'       => _w('Самовывоз'),
        'post_name'              => _w('Почта'),
        'address_fields'         => array(),
        'service_agreement'      => false,
        'service_agreement_hint' => _w('Оформляя заказ, вы подтверждаете своё совершеннолетие и соглашаетесь на <a href="">обработку персональных данных</a>'),
        'plugin_timeout'         => 10, // In seconds
    ),
    'payment'      => array(
        'used'       => false,
        'block_name' => _w('Оплата')
    ),
    'confirmation' => array(
        'order_comment'      => true,
        'terms'              => false,
        'terms_text'         => '',
        'order_without_auth' => shopCheckoutConfig::ORDER_WITHOUT_AUTH_CREATE,
        'auth_with_code'     => true,
        'recode_timeout'     => 60, // In seconds
        'thankyou_header'    => _w('Заказ оформлен. Спасибо!'),
        'thankyou_content'   => _w('Мы свяжемся с вами в ближайшее время. Номер вашего заказа #$order_id.'),
    ),
);