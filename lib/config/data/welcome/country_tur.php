<?php
return array(
    'currency' => array(
        'TRY' => 1.0,
        'USD' => 36.38,
        'EUR' => 38.80,
    ),
    'taxes'    => array(
        array(
            'name'         => 'KDV',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'tur' => array(
                    'global_rate' => 18,
                ),
            ),
        ),
    ),
);