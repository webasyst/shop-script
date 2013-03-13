<?php

return array(
    'contactinfo' => array(
        'name' => _w('Contact info'),
        'description' => _w('Configure the list of fields requested during the checkout.'),
        'fields' => array(
            'firstname' => array(),
            'lastname' => array(),
            'phone' => array(),
            'email' => array(),
            'address.shipping' => array()
        )
    ),
    'shipping' => array(
        'name' => _w('Shipping'),
        'description' => _w('The shipping checkout step prompts user to select the shipping type.')
    ),
    'payment' => array(
        'name' => _w('Payment'),
    ),
    'confirmation' => array(
        'name' => _w('Confirmation')
    )
);