<?php

return array(
    'name' => /*_wp*/('301 Redirect'),
    'description' => /*_wp*/('Helps you migrate to Shop-Script from third-party ecommerce platforms keeping all your product and storefront pages redirected and indexed properly.'),
    'vendor' => 'webasyst',
    'version' => '1.1',
    'img'=>'img/redirect.png',
    'icons'=>array(
        16 => 'img/redirect.png',
    ),
    'shop_settings'=>true,
    'handlers' => array(
        'frontend_error' => 'frontendError',
        'frontend_search' => 'frontendSearch'
    ),
);
//EOF
