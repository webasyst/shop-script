<?php
return array(
    'currency' => array(
        'IRR' => 1.0,
        'USD' => 883100.00,
        'EUR' => 900000.00,
    ),
    'taxes'    => array(
        array(
            'name'         => 'VAT',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'irn' => array(
                    'global_rate' => 9,
                ),
            ),
        ),
    ),
);