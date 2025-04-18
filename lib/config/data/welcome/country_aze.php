<?php
return array(
    'currency' => array(
        'AZN' => 1.0,
        'USD' => 0.6,
        'EUR' => 1.8,
    ),
    'taxes'    => array(
        array(
            'name'         => 'ƏDV',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'aze' => array(
                    'global_rate' => 18,
                ),
            ),
        ),
    ),
);
