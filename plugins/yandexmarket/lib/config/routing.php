<?php
return array(
    'yandexmarket/shops.dtd'                                                               => array(
        'plugin' => 'yandexmarket',
        'module' => 'frontend',
        'action' => 'dtd',
    ),
    "yandexmarket/<hash:[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}>.xml" => array(
        'plugin' => 'yandexmarket',
        'module' => 'frontend',
        'action' => 'catalog',
    ),
);



