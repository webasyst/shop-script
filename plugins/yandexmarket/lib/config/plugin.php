<?php

return array(
    'name'           => 'Яндекс.Маркет',
    'description'    => 'Экспорт каталога товаров в формате YML',
    'img'            => 'img/yandexmarket.png',
    'vendor'         => 'webasyst',
    'version'        => '1.3.7',
    'importexport'   => 'profiles',
    'export_profile' => true,
    'frontend'       => true,
    'handlers'       => array(
        'backend_products'         => 'backendProductsEvent',
        'backend_reports'          => 'backendReportsEvent',
        'backend_reports_channels' => 'backendReportsChannelsEvent',
        'backend_category_dialog'  => 'backendCategoryDialog',
        'category_save'            => 'categorySaveHandler',
        'order_action.ship'        => 'orderStatus',
        'order_action.complete'    => 'orderStatus',
        'order_action.delete'      => 'orderStatus',
    ),
);
