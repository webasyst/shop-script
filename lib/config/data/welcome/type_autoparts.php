<?php

return array(
    'name' => 'Auto parts',
    'icon' => 'ss pt car',
    'features' => array(
        'manufacturer' => array(
            'name' => 'Manufacturer',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true
        ),
        'country' => array(
            'name' => 'Country of origin',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true
        ),
        'suitable_models' => array(
            'name' => 'Suitable automobile models',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple' => true
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
    ),
);
