<?php
return array(
    'currency' => array(
        'KRW' => 1.0,
        'USD' => 1433.54,
        'EUR' => 1546.20,
    ),
    'taxes'    => array(
        array(
            'name'         => '부가가치세',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'kor' => array(
                    'global_rate' => 10,
                ),
            ),
        ),
    ),
);