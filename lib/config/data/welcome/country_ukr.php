<?php
return array(
    'currency' => array(
        'UAH' => 1.0,
        'USD' => 25,
        'EUR' => 30,
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
