<?php

return array(
    'name' => /*_wp*/('Brands'),
    'description' => /*_wp*/('Storefront’s product filtering by brand (manufacturer)'),
    'vendor'=>'webasyst',
    'version'=>'1.0.0',
    'img'=>'img/brands.png',
    'shop_settings' => true,
    'frontend'    => true,
    'icons'=>array(
        16 => 'img/brands.png',
    ),
    'handlers' => array(
        'frontend_nav' => 'frontendNav'
    ),
);
//EOF
