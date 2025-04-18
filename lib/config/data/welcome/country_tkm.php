<?php
return array(
    'currency' => array(
        'TMT' => 1.0,
        'USD' => 3.50,
        'EUR' => 3.65,
    ),
    'taxes'    => array(
        array(
            'name'         => 'GBS',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'tkm' => array(
                    'global_rate' => 15,
                ),
            ),
        ),
    ),
);