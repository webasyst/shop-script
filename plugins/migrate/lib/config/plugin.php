<?php

return array(
    'name'         => /*_wp*/('Migrate to Shop-Script'),
    'description'  => /*_wp*/('Transfer data from other ecommerce platforms to Shop-Script'),
    'img'          => 'img/migrate.png',
    'vendor'       => 'webasyst',
    'version'      => '2.1.0',
    'importexport' => true,
    'handlers'     => array(
        'backend_welcome'=>'backendWelcomeHandler',
    ),
);
