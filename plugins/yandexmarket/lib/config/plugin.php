<?php

return array(
    'name'            => 'Яндекс.Маркет',
    'description'     => 'Экспорт каталога товаров в формате YML, прием заказов (CPA)',
    'img'             => 'img/yandexmarket.png',
    'vendor'          => 'webasyst',
    'version'         => '2.2.6',
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

        'order_action.ship'        => 'orderActionHandler',
        'order_action.complete'    => 'orderActionHandler',
        'order_action.delete'      => 'orderActionHandler',
        'order_action.*'           => 'orderActionHandler',

        'order_action_form.delete' => 'orderDeleteFormHandler',
        'order_action_form.*'      => 'orderDeleteFormHandler',

        'currency_delete'          => 'currencyDeleteHandler',
    ),
);
