<?php

return array(
    'name' => 'Audio',
    'icon' => 'ss pt audio',
    'features' => array(
        'brand' => array(
            'name' => 'Brand',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(),
        ),
        'channels' => array(
            'name' => 'Channels',
            'type' => shopFeatureModel::TYPE_DOUBLE,
            'selectable' => true,
            'values' => array(1, 2, 2.5, 3, 3.5, 4, 4.5, 5)
        ),
        'type' => array(
            'name' => 'Type',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable',
            'values' => array(
                'active',
                'passive'
            )
        ),
        'wattage' => array(
            'name' => 'Wattage',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.power',
        ),
        'weight' => array(
            'name' => 'Weight',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.weight',
            'builtin' => 1
        ),
        'gtin' => array(
            'name' => 'GTIN',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'status' => shopFeatureModel::STATUS_PRIVATE,
            'available_for_sku' => 1,
            'builtin' => 1
        ),
    )
);
