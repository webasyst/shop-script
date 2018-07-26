<?php
return array(
    'name'             => /*_w*/('Store'),
    'description'      => /*_w*/('Shop-Script is a robust shopping cart software that allows you to quickly establish your own online store and sell online.'),
    'icon'             => array(
        16 => 'img/shop16.png',
        24 => 'img/shop24.png',
        48 => 'img/shop.png',
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
    'version'          => '7.5.0', // developer preview RC2
    'critical'         => '7.0.0',
    'vendor'           => 'webasyst',
    'csrf'             => true,
    'payment_plugins'  => array(
        'taxes'        => true,
    ),
    'shipping_plugins' => array(
        'desired_date'  => true,
        'draft'         => true,
        'ready'         => true,
        'cancel'        => true,
        'taxes'         => true,
        'custom_fields' => true,
    ),
    'sms_plugins'      => true,
    'license'          => 'commercial',
);
