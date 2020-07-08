<?php

return array(
    'name'     => 'Jewelry',
    'icon'     => 'ss pt diamond',
    'features' => array(
        'metal' => array(
            'name'   => 'Metal',
            'type'   => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple'   => true,
            'values' => array(
                'gold',
                'silver',
                'platinum',
                'palladium',
                'iridium',
                'osmium',
                'rhodium',
                'ruthenium',
            ),
        ),
        'weight' => array(
            'name' => 'Weight',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.weight',
            'builtin' => 1
        ),
        'fineness' => array(
            'name' => 'Fineness',
            'type' => shopFeatureModel::TYPE_DOUBLE,
            'selectable' => true,
            'multiple'   => true,
            'values' => array(999.99, 999.9, 999, 995, 990, 986, 980, 958.3, 958, 950, 925, 916, 900, 850, 835, 833, 830, 800, 750, 585, 417, 375, 333),
        ),
        'gemstones' => array(
            'name' => 'Gemstones',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple'   => true,
            'values' => array(),
        ),
        'gtin' => array(
            'name' => 'GTIN',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'status' => shopFeatureModel::STATUS_PRIVATE,
            'available_for_sku' => 1,
            'builtin' => 1
        ),
    ),
);
