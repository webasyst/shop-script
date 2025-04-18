<?php
return array(
    'currency' => array(
        'UZS' => 1.0,
        'USD' => 12900,
        'EUR' => 13510,
    ),
    'taxes'    => array(
        array(
            'name'         => 'QQS',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'uzb' => array(
                    'global_rate' => 12,
                ),
            ),
        ),
    ),
);