<?php
return array(
    'currency' => array(
        'CAD' => 1.0,
        'USD' => 1.0,
    ),
    'taxes' => array(
        array(
            'name'         => 'GST',
            'included'     => false,
            'address_type' => 'shipping',
            'countries'    => array(
                'can' => array(
					'global_rate' => 5,
					'regions' => array(
						'AB' => array(
							'name' => 'GST',
							'tax_value' => 0,
							'tax_value_modifier' => '+'
						),
						'BC' => array(
							'name' => 'HST',
							'tax_value' => 7,
							'tax_value_modifier' => ''
						),
						'MB' => array(
							'name' => 'GST + PST',
							'tax_value' => 7,
							'tax_value_modifier' => '+'
						),
						'NB' => array(
							'name' => 'HST',
							'tax_value' => 8,
							'tax_value_modifier' => ''
						),
						'NL' => array(
							'name' => 'HST',
							'tax_value' => 8,
							'tax_value_modifier' => ''
						),
						'NS' => array(
							'name' => 'HST',
							'tax_value' => 10,
							'tax_value_modifier' => ''
						),
						'NT' => array(
							'name' => 'GST',
							'tax_value' => 0,
							'tax_value_modifier' => '+'
						),						
						'NU' => array(
							'name' => 'GST',
							'tax_value' => 0,
							'tax_value_modifier' => '+'
						),						
						'ON' => array(
							'name' => 'HST',
							'tax_value' => 8,
							'tax_value_modifier' => ''
						),
						'PE' => array(
							'name' => 'GST + PST',
							'tax_value' => 10,
							'tax_value_modifier' => '*'
						),
						'QC' => array(
							'name' => 'GST + QST',
							'tax_value' => 9.975,
							'tax_value_modifier' => '+'
						),
						'SK' => array(
							'name' => 'GST + PST',
							'tax_value' => 5,
							'tax_value_modifier' => '+'
						),
						'YT' => array(
							'name' => 'GST',
							'tax_value' => 0,
							'tax_value_modifier' => '+'
						),
					),
				),
			),
        ),
    ),    
);
