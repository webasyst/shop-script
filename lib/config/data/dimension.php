<?php
return array(
    'length' => array(
        'name'      => _w('Length'),
        'base_unit' => 'm',
        'units'     => array(
            'm'  => array(
                'name'       => _w('m'), //meter
                'multiplier' => 1,
            ),
            'cm' => array(
                'name'       => _w('cm'), //centimeter
                'multiplier' => 0.01,
            ),
            'mm' => array(
                'name'       => _w('mm'), //millimeter
                'multiplier' => 0.001,
            ),
            'in' => array(
                'name'       => _w('in'), //inch
                'multiplier' => 0.0254,
            ),
            'ft' => array(
                'name'       => _w('ft'), //foot
                'multiplier' => 0.3048,
            ),
            'yd' => array(
                'name'       => _w('yd'), //yard
                'multiplier' => 0.9144,
            ),
            'km' => array(
                'name'       => _w('km'), //kilometer
                'multiplier' => 1000,
            ),
            'mi' => array(
                'name'       => _w('mi'), //mile
                'multiplier' => 1609,
            ),
        )
    ),
    'weight' => array(
        'name'      => _w('Weight'),
        'base_unit' => 'kg',
        'units'     => array(
            'kg'  => array(
                'name'       => _w('kg'), //kilogram
                'multiplier' => 1,
            ),
            'g'   => array(
                'name'       => _w('g'), //gram
                'multiplier' => 0.001,
            ),
            'lbs' => array(
                'name'       => _w('lbs'), //pound
                'multiplier' => 0.4536,
            ),
            'oz'  => array(
                'name'       => _w('oz'), //ounce
                'multiplier' => 0.0283495,
            ),
        ),
    ),
    'volume' => array(
        'name'      => _w('Volume'),
        'base_unit' => 'l',
        'units'     => array(
            'l'  => array(
                'name'       => _w('l'), //liter
                'multiplier' => 1,
            ),
            'ml' => array(
                'name'       => _w('ml'), //milliliter
                'multiplier' => 0.001,
            ),
            'cm3' => array(
                'name' => _w('cm3'), //cubic centimeter
                'multiplier' => 0.001,
            ),
            'm3' => array(
                'name' => _w('m3'), //cubic meter
                'multiplier' => 1000,
            ),
            'cl' => array(
                'name'       => _w('cl'), //centiliter
                'multiplier' => 0.4536,
            ),
            'mm3' => array(
                'name' => _w('mm3'), //cubic millimeter
                'multiplier' => 0.000001,
            ),
        ),
    ),
    'frequency' => array(
        'name'      => _w('Frequency'),
        'base_unit' => 'Hz',
        'units'     => array(
            'Hz'  => array(
                'name'       => _w('Hz'),
                'multiplier' => 1,
            ),
            'KHz'  => array(
                'name'       => _w('KHz'),
                'multiplier' => 1000,
            ),
            'MHz'  => array(
                'name'       => _w('MHz'),
                'multiplier' => 1000000,
            ),
            'GHz'  => array(
                'name'       => _w('GHz'),
                'multiplier' => 1000000000,
            ),
        ),
    ),
    'power' => array(
        'name'      => _w('Power'),
        'base_unit' => 'W',
        'units'     => array(
            'W'  => array(
                'name'       => _w('W'),
                'multiplier' => 1,
            ),
            'KW'  => array(
                'name'       => _w('KW'),
                'multiplier' => 1000,
            ),
            'MW'  => array(
                'name'       => _w('MW'),
                'multiplier' => 1000000,
            ),
            'mW'  => array(
                'name'       => _w('mW'),
                'multiplier' => 0.001,
            ),
        ),
    ),
    'memory' => array(
        'name'      => _w('Memory'),
        'base_unit' => 'MB',
        'units'     => array(
            'B'  => array(
                'name'       => _w('B'),
                'multiplier' => 0.000001,
            ),
            'KB'  => array(
                'name'       => _w('KB'),
                'multiplier' => 0.001,
            ),
            'MB'  => array(
                'name'       => _w('MB'),
                'multiplier' => 1,
            ),
            'GB'  => array(
                'name'       => _w('GB'),
                'multiplier' => 1000,
            ),
            'TB'  => array(
                'name'       => _w('TB'),
                'multiplier' => 1000000,
            ),
        ),
    ),
    'area' => array(
        'name' => _w('Area'),
        'base_unit' => 'sqm',
        'units' => array(
            'sqm' => array(
                'name'       => _w('sq m'),
                'multiplier' => 1,
            ),
            'sqft' => array(
                'name'       => _w('sq ft'),
                'multiplier' => 0.092903
            ),
            'ac' => array(
                'name'       => _w('ac'),
                'multiplier' => 4046.86
            ),
            'ha' => array(
                'name'       => _w('ha'),
                'multiplier' => 10000
            ),
            'sqmm' => array(
                'name'       => _w('sq mm'),
                'multiplier' => 0.000001,
            ),
            'sqcm' => array(
                'name'       => _w('sq cm'),
                'multiplier' => 0.0001,
            ),
            'sqyd' => array(
                'name'       => _w('sq yd'),
                'multiplier' => 0.836127,
            ),
            'sqin' => array(
                'name'       => _w('sq in'),
                'multiplier' => 0.00064516,
            ),
            'sqkm' => array(
                'name'       => _w('sq km'),
                'multiplier' => 1000000,
            ),
            'sqmi' => array(
                'name'       => _w('sq mi'),
                'multiplier' => 2589975.2356,
            )
        )
    ),
    'time' => array(
        'name' => _w('Time'),
        'base_unit' => 'sec',
        'units' => array(
            'sec' => array(
                'name' => _w('sec'),
                'multiplier' => 1
            ),
            'min' => array(
                'name' => _w('min'),
                'multiplier' => 60
            ),
            'hr' => array(
                'name' => _w('hr'),
                'multiplier' => 3600
            ),
            'day' => array(
                'name' => _w('days'),
                'multiplier' => 86400
            ),
            'week' => array(
                'name' => _w('weeks'),
                'multiplier' => 604800
            ),
            'month' => array(
                'name' => _w('months'),
                'multiplier' => 2592000
            ),
            'year' => array(
                'name' => _w('years'),
                'multiplier' => 31536000
            ),
        )
    ),
    'voltage' => array(
        'name' => _w('Voltage'),
        'base_unit' => 'V',
        'units' => array(
            'mV' => array(
                'name' => _w('mV'),
                'multiplier' => 0.001,
            ),
            'V'=> array(
                'name' => _w('V'),
                'multiplier' => 1
            ),
            'KV' => array(
                'name' => _w('KV'),
                'multiplier' => 1000
            ),
            'MV' => array(
                'name' => _w('MV'),
                'multiplier' => 1000000
            )
        )
    ),
    'temperature' => array(
        'name' => _w('Temperature'),
        'base_unit' => '°C',
        'units' => array(
            '°C' => array(
                'name' => '°C',
                'multiplier' => 1
            ),
            'K' => array(
                'name' => 'K',
                'multiplier' => 1,
            ),
            '°F' => array(
                'name' => '°F',
                'multiplier' => 1,
            )
        )
    ),
    'amperage' => array(
        'name' => _w('Amperage'),
        'base_unit' => 'A',
        'units' => array(
            'mA' => array(
                'name' => _w('mA'),
                'multiplier' => 0.001,
            ),
            'A' => array(
                'name' => _w('A'),
                'multiplier' => 1
            ),
            'KA' => array(
                'name' => _w('KA'),
                'multiplier' => 1000,
            ),
            'MA' => array(
                'name' => _w('MA'),
                'multiplier' => 1000000,
            )
        )
    ),
    'electric-charge' => array(
        'name' => _w('Electric charge'),
        'base_unit' => 'Ah',
        'units' => array(
            'mAh' => array(
                'name' => _w('mAh'),
                'multiplier' => 0.001,
            ),
            'Ah' => array(
                'name' => _w('Ah'),
                'multiplier' => 1,
            ),
            'KAh' => array(
                'name' => _w('KAh'),
                'multiplier' => 1000,
            ),
            'MAh' => array(
                'name' => _w('MAh'),
                'multiplier' => 1000000,
            )
        )
    )

    //custom dimensions are added in a similar way

);
