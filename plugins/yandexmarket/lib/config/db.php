<?php
return array(
    'shop_yandexmarket_campaigns' => array(
        'id'    => array('int', 11, 'null' => 0, 'default' => '0'),
        'name'  => array('varchar', 64, 'null' => 0, 'default' => ''),
        'value' => array('text'),
        ':keys' => array(
            'PRIMARY' => array('id', 'name'),
        ),
    ),
);
