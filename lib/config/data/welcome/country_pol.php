<?php
return array(
    'currency' => array(
        'PLN' => 1.0,
        'USD' => 4.0,
        'EUR' => 4.1,
    ),
    'taxes'    => array(
        array(
            'name'         => 'VAT',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'pol' => array(
                    'global_rate' => 23,
                ),
            ),
        ),
    ),
);