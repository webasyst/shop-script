<?php
return array(
    'currency' => array(
        'KZT' => 1.0,
        'USD' => 502,
        'EUR' => 525,
    ),
    'taxes'    => array(
        array(
            'name'         => 'ҚҚС',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'kaz' => array(
                    'global_rate' => 12,
                ),
            ),
        ),
    ),
);
