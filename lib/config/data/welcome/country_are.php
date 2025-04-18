<?php
return array(
    'currency' => array(
        'AED' => 1.0,
        'USD' => 3.67,
        'EUR' => 4.00,
    ),
    'taxes'    => array(
        array(
            'name'         => 'VAT',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'uae' => array(
                    'global_rate' => 5,
                ),
            ),
        ),
    ),
);