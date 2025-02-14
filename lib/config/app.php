<?php

return array(
    'name'             => /*_w*/('Shop-Script'),
    'description'      => /*_w*/('Shop-Script is a robust shopping cart software that allows you to quickly establish your own online store and sell online.'),
    'icon'             => 'img/shop.svg',
    'sash_color'       => '#27bf52',
    'rights'           => true,
    'frontend'         => true,
    'auth'             => true,
    'themes'           => true,
    'plugins'          => true,
    'pages'            => true,
    'mobile'           => true,
    'my_account'       => true,
    'version'          => '11.2.1',
    'critical'         => '11.2.1',
    'vendor'           => 'webasyst',
    'csrf'             => true,
    'payment_plugins'  => array(
        'taxes'     => true,
        'rights'    => 'settings',
    ),
    'shipping_plugins' => array(
        'desired_date'  => true,
        'draft'         => true,
        'ready'         => true,
        'cancel'        => true,
        'taxes'         => true,
        'custom_fields' => true,
        'dimensions'    => false,   //actual value casted via shopShipping::getAppProperties
        'sync'          => true,    //actual value casted via shopShipping::getAppProperties
        'callback'      => array(), //actual value casted via shopShipping::getAppProperties
        'rights'        => 'settings',
    ),
    'sms_plugins'      => true,
    'license'          => 'commercial',
    'routing_params'   => array(
        'checkout_version'       => 2,
        'checkout_storefront_id' => class_exists('shopCheckoutConfig') ? ['shopCheckoutConfig', 'generateStorefrontId'] : md5(uniqid()),
    ),
    'ui'               => '1.3,2.0'
);
