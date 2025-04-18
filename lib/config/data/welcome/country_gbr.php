<?php
return array(
    'currency' => array(
        'GBP' => 1.0,
        'USD' => 0.79,
        'EUR' => 0.95,
    ),
    'taxes'    => array(
        array(
            'name'         => 'VAT',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'gbr' => array(
                    'global_rate' => 20,
                ),
            ),
        ),
    ),
);