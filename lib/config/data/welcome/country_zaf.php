<?php
return array(
    'currency' => array(
        'ZAR' => 1.0,
        'USD' => 18,
        'EUR' => 19,
    ),
    'taxes'    => array(
        array(
            'name'         => 'VAT',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'zaf' => array(
                    'global_rate' => 15,
                ),
            ),
        ),
    ),
);