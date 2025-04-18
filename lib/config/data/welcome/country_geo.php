<?php
return array(
    'currency' => array(
        'GEL' => 1.0,
        'USD' => 2.75,
        'EUR' => 2.95,
    ),
    'taxes'    => array(
        array(
            'name'         => 'დღგ',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'geo' => array(
                    'global_rate' => 18,
                ),
            ),
        ),
    ),
);