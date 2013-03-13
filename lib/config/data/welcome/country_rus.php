<?php
return array(
    'currency' => array(
        'RUB' => 1.0,
        'USD' => 30,
        'EUR' => 40,
    ),
    'taxes' => array(
        array(
            'name'         => 'НДС',
            'included'     => true,
            'address_type' => 'shipping',
            'countries'    => array(

                'rus' => array(
                    'global_rate' => 18, // %
				),

                // Use special codes instead of country ISO3 for country groups:
                // '%AL' = All countries
                // '%EU' = All european countries
                // '%RW' = Rest of the world
                
            ),
        ),
    ),
);
