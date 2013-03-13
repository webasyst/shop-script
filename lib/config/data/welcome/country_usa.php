<?php
return array(
    'currency' => array(
        'USD' => 1.0,
    ),
    'taxes' => array(
        array(
            'name' => 'Sales tax',
            'included' => false,
            'address_type' => 'shipping',
            'countries' => array(
                'usa' => array(
                    'regions' => array(
                        'AL' => 4,
                        'AK' => 0,
                        'AZ' => 6.6,
                        'AR' => 6,
                        'CA' => 7.5,
                        'CO' => 2.9,
                        'CT' => 6.35,
                        'DE' => 0,
                        'FL' => 6,
                        'GA' => 4,
                        'HI' => 4,
                        'ID' => 6,
                        'IL' => 6.25,
                        'IN' => 7,
                        'IA' => 6,
                        'KS' => 6.3,
                        'KY' => 6,
                        'LA' => 4,
                        'ME' => 5,
                        'MD' => 6,
                        'MA' => 6.25,
                        'MI' => 6,
                        'MN' => 7.275,
                        'MS' => 7,
                        'MO' => 4.225,
                        'MT' => 0,
                        'NE' => 5.5,
                        'NV' => 6.85,
                        'NH' => 0,
                        'NJ' => 7,
                        'NC' => 4.75,
                        'ND' => 5,
                        'OH' => 5.5,
                        'OK' => 4.5,
                        'OR' => 0,
                        'PA' => 6,
                        'RI' => 7,
                        'SC' => 6,
                        'SD' => 4,
                        'TN' => 7,
                        'TX' => 6.25,
                        'UT' => 5.95,
                        'VT' => 6,
                        'VA' => 5,
                        'WA' => 6.5,
                        'WV' => 6,
                        'WI' => 5,
                        'WY' => 4,
                    ),
                ),
                
                // Use special codes instead of country ISO3 for country groups:
                // '%AL' = All countries
                // '%EU' = All european countries
                // '%RW' = Rest of the world
                
            ),
        ),
    ),
);
