<?php

return array(
    'name' => 'Smart Filters',
    'description' => 'Плагин фильтрации в категориях',
	'author'=>'Hardman',
    'vendor'=>'hardman',
    'version'=>'1.0.0',
    'img'=>'img/smartfilters.gif',
	'handlers'=> array(
        'frontend_category' => 'frontendCategory'
    ),
    'shop_settings' => true,
	'slug' => "shop/plugins/brands",
	'app_slug' => 'shop'
);
//EOF
