<?php

return array(
    'name'             => /*_w*/('Store'),
    'description'      => /*_w*/('Shop-Script is a robust shopping cart software that allows you to quickly establish your own online store and sell online.'),
    'icon'             => array(
        16 => 'img/shop16.png',
        24 => 'img/shop24.png',
        48 => 'img/shop48.png',
        96 => 'img/shop96.png',
    ),
    'sash_color'       => '#27bf52',
    'rights'           => true,
    'frontend'         => true,
    'auth'             => true,
    'themes'           => true,
    'plugins'          => true,
    'pages'            => true,
    'mobile'           => true,
    'my_account'       => true,
    'version'          => '8.18.0',
    'critical'         => '8.0.0',
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
);
