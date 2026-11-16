<?php
return array(
    'currency' => array(
        'BYN' => 1.0,
        'USD' => 3,
        'EUR' => 3.5,
    ),
    'taxes'    => array(
        array(
            'name'         => 'НДС',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'blr' => array(
                    'global_rate' => 20,
                ),
            ),
        ),
    ),
);
