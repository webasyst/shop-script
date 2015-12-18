<?php

return array(
    'name'        => 'debug',
    'title'       => ('Debug'),
    'description' => ('Debug plugins interaction'),
    'vendor'      => 'webasyst',
    'version'     => '0.0.1',
    'handlers'    => array(
        /* frontend UI hooks */
        /* backend UI hooks */
        'backend_menu'=>'backendMenu',
        'backend_orders'=>'backendOrders',
        /* data hooks */
        'product_delete' => 'productDeleteHandler',
        'product_save'   => 'productSaveHandler',
        'product_custom_fields'=>'productCustomFieldsHandler',
        /* frontend UI hooks */

        'backend_product_sku_settings' => 'backendProductSkuSettings',
        'order_calculate_discount' => 'orderCalculateDiscount',

        'frontend_products' => 'frontendProducts',

    ),
);
//EOF
