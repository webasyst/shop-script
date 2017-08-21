<?php
$__h = '[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}';
return array(
    'yandexmarket/shops.dtd'         => array(
        'plugin' => 'yandexmarket',
        'module' => 'frontend',
        'action' => 'dtd',
    ),
    "yandexmarket/<hash:{$__h}>.xml" => array(
        'plugin' => 'yandexmarket',
        'module' => 'frontend',
        'action' => 'catalog',
    ),

    "yandexmarket/api/order/accept" => array(
        'plugin' => 'yandexmarket',
        'module' => 'api',
        'action' => 'orderAccept',
    ),
    "yandexmarket/api/order/status" => array(
        'plugin' => 'yandexmarket',
        'module' => 'api',
        'action' => 'orderStatus',
    ),
    "yandexmarket/api/cart"         => array(
        'plugin' => 'yandexmarket',
        'module' => 'api',
        'action' => 'cart',
    ),
    "yandexmarket/api"         => array(
        'plugin' => 'yandexmarket',
        'module' => 'api',
        'action' => 'hello',
    ),
    "yandexmarket/api/"         => array(
        'plugin' => 'yandexmarket',
        'module' => 'api',
        'action' => 'hello',
    ),
);
