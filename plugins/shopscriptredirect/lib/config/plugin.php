<?php

return array(
    'name' => /*_wp*/('WebAsyst Shop-Script 301 Redirect'),
    'description' => /*_wp*/('Automatic redirect from your old WebAsyst Shop-Script storefront URLs'),
    'vendor'=>'webasyst',
    'version'=>'1.0.0',
    'img'=>'img/redirect.png',
    'icons'=>array(
        16 => 'img/redirect.png',
    ),
    'handlers' => array(
        'frontend_error' => 'frontendError',
        'frontend_search' => 'frontendSearch'
    ),
);
//EOF
