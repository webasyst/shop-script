<?php

return array(
    'name'         => '1С (CommerceML)',
    'description'  => 'Обмен данными с «1С: Управление торговлей» (CommerceML)',
    'img'          => 'img/1c.png',
    'vendor'       => 'webasyst',
    'version'      => '2.2.2',
    'importexport' => true,
    'frontend'     => true,
    'handlers'     => array(
        'backend_product'              => 'productHandler',
        'backend_product_sku_settings' => 'skuHandler',
    ),
    'locale'       => array(
        'ru_RU',
    ),
);
