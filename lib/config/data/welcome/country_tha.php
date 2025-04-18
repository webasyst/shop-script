<?php
return array(
    'currency' => array(
        'THB' => 1.0,
        'USD' => 33.9,
        'EUR' => 35.2,
    ),
    'taxes'    => array(
        array(
            'name'         => 'VAT',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'tha' => array(
                    'global_rate' => 7,
                ),
            ),
        ),
    ),
);