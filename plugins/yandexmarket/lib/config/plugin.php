<?php

return array(
    'name'            => 'Экспорт в YML (Яндекс Маркет)',
    'description'     => 'Экспорт товаров в формате YML',
    'img'             => 'img/yandexmarket.png',
    'vendor'          => 'webasyst',
    'version'         => '2.5.1',
    'importexport'    => 'profiles',
    'export_profile'  => true,
    'custom_settings' => true,
    'frontend'        => true,
    'handlers' => array(
        'backend_products'         => 'backendProductsEvent',
        'backend_reports'          => 'backendReportsEvent',
        'backend_reports_channels' => 'backendReportsChannelsEvent',
        'backend_category_dialog'  => 'backendCategoryDialog',
        'backend_order'            => 'backendOrderEvent',

        'category_save'            => 'categorySaveHandler',

        'currency_delete'          => 'currencyDeleteHandler',
    ),
);