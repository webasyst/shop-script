<?php

return array(
    'name'         => '1С',
    'description'  => 'Обмен данными с «1С: Управление торговлей» (CommerceML)',
    'img'          => 'img/1c.png',
    'vendor'       => 'webasyst',
    'version'      => '2.0.0',
    'importexport' => true,
    'frontend'     => true,
    'handlers'     => array(
        'products_collection' => 'productsCollection',
    ),
    'locale'       => array(
        'ru_RU',
    ),
);

//EOF