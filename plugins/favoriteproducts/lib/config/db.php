<?php
return array(
    'shop_favoriteproducts' => array(
        'contact_id' => array('int', 11, 'null' => 0),
        'product_id' => array('int', 11, 'null' => 0),
        'datetime' => array('datetime', 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('contact_id', 'product_id'),
        ),
    ),
);
