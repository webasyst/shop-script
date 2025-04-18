<?php
return array(
    'currency' => array(
        'JPY' => 1.0,
        'USD' => 151.74,
        'EUR' => 162.80,
    ),
    'taxes'    => array(
        array(
            'name'         => '消費税',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'jpn' => array(
                    'global_rate' => 10,
                ),
            ),
        ),
    ),
);