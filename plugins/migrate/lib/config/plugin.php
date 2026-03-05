<?php

return array(
    'name'         => /*_wp*/('Migrate to Shop-Script'),
    'description'  => /*_wp*/('Transfer data from other ecommerce platforms to Shop-Script'),
    'img'          => 'img/migrate.svg',
    'vendor'       => 'webasyst',
    'version'      => '2.4.1',
    'importexport' => true,
    'handlers'     => array(
        'backend_welcome' => 'backendWelcomeHandler',
    ),
);