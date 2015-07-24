<?php

return array(
    'name'           => 'Яндекс.Маркет',
    'description'    => 'Экспорт каталога товаров в формате YML',
    'img'            => 'img/yandexmarket.png',
    'vendor'         => 'webasyst',
    'version'        => '1.2.4',
    'importexport'   => 'profiles',
    'export_profile' => true,
    'frontend'       => true,
    'handlers'       => array(
        'backend_products'        => 'backendProductsEvent',
        'backend_category_dialog' => 'backendCategoryDialog',
        'category_save'           => 'categorySaveHandler',
    ),
);
