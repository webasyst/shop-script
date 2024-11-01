<?php
return array(
    'currency' => array(
        'UAH' => 1.0,
        'USD' => 40,
        'EUR' => 45,
        'RUB' => 0.4,
    ),
    'taxes'    => array(
        array(
            'name'         => 'НДС',
            'included'     => false,
            'address_type' => 'shipping',
            'countries'    => array(
                'ukr' => array(
                    'global_rate' => 20,
                ),
            ),
        ),
    ),
);
