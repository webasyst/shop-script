<?php
return array(
    'currency' => array(
        'INR' => 1.0,
        'USD' => 83.10,
        'EUR' => 89.45,
    ),
    'taxes'    => array(
        array(
            'name'         => 'GST',
            'included'     => false,
            'address_type' => 'shipping',
            'countries'    => array(
                'ind' => array(
                    'global_rate' => 18,
                ),
            ),
        ),
    ),
);