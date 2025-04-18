<?php
return array(
    'currency' => array(
        'CNY' => 1.0,
        'USD' => 7.25,
        'EUR' => 7.85,
    ),
    'taxes'    => array(
        array(
            'name'         => '增值税',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'chn' => array(
                    'global_rate' => 13,
                ),
            ),
        ),
    ),
);