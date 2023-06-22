<?php

return array(
    'name' => 'Musical instruments',
    'icon' => 'ss pt guitar',
    'features' => array(
        'country' => array(
            'name' => 'Country of origin',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true
        ),
        'brand' => array(
            'name' => 'Brand',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true
        ),
        'type' => array(
            'name' => 'Type',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                'acoustic',
                'electronic',
                'sime-acoustic'
            )
        ),
        'material' => array(
            'name' => 'Material',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple' => true
        ),
        'weight' => array(
            'name' => 'Weight',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.weight',
            'available_for_sku' => 1,
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
