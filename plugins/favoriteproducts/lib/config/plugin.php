<?php

return array(
    'name' => /*_wp*/('Favorite products'),
    'description' => /*_wp*/('Backend favorite products filtering'),
    'vendor'=>'webasyst',
    'version'=>'1.0.0',
    'img'=>'img/star.png',
    'icons'=>array(
        16 => 'img/star.png',
    ),
    'handlers' => array(
        'products_collection' => 'productsCollection',
        'backend_products' => 'backendProducts',
        'backend_product' => 'backendProduct',
        'product_delete' => 'productDelete',
        'reset' => 'reset',
    ),
);
//EOF
