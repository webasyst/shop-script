<?php

return array(
    'name'         => '1С (CommerceML)',
    'description'  => 'Обмен данными с системами товарного учета («1С», «МойСклад», «Бизнес.ру» и подобные).',
    'img'          => 'img/1c.png',
    'vendor'       => 'webasyst',
    'version'      => '2.5.13',
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
