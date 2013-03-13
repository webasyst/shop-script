<?php
return array(
    'currency' => array(
        'UAH' => 1.0,
        'USD' => 8,
        'EUR' => 10.8,
        'RUB' => 0.27,
    ),
    'taxes' => array(
		array(
			'name' => 'НДС',
			'included' => false,
			'address_type' => 'shipping',
			'countries' => array(
				'ukr' => array(
				  'global_rate' => 20,
				),
			),
		),
    ),
);