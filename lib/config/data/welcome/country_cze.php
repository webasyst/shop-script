<?php
return array(
    'currency' => array(
        'CZK' => 1.0,
        'USD' => 22.0,
        'EUR' => 25.0,
    ),
    'taxes'    => array(
        array(
            'name'         => 'DPH',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'cze' => array(
                    'global_rate' => 21,
                ),
            ),
        ),
    ),
);