<?php
return array(
    'currency' => array(
        'IDR' => 1.0,
        'USD' => 16320,
        'EUR' => 16800,
    ),
    'taxes'    => array(
        array(
            'name'         => 'PPN',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'idn' => array(
                    'global_rate' => 12,
                ),
            ),
        ),
    ),
);