<?php
return array(
    'name' => /*_wp*/('Order generation'),
    'description' => /*_wp*/('Bulk order and customer creation for demo accounts and testing purposes'),
    'vendor' => 'webasyst',
    'version' => '1.0.0',
    'img' => 'img/genorder16.png',
    'icons'=>array(
        16 => 'img/genorder16.png',
    ),
    'handlers' => array(
        'backend_menu'   => 'backendMenu', // 1.3 tab main menu
        'backend_extended_menu' => 'backendExtendedMenu', // 2.0 sidebar main menu
    ),
);
