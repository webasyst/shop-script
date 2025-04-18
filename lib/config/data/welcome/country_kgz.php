<?php
return array(
    'currency' => array(
        'KGS' => 1.0,
        'USD' => 87,
        'EUR' => 91,
    ),
    'taxes'    => array(
        array(
            'name'         => 'КНС',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(
                'kgz' => array(
                    'global_rate' => 12,
                ),
            ),
        ),
    ),
);
