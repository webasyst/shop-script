<?php

return array(
    'name' => 'Food & drinks',
    'icon' => 'ss pt hamburger',
    'features' => array(
        'country' => array(
            'name' => 'Country of origin',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true
        ),
        'weight' => array(
            'name' => 'Packaging weight',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.weight',
            'builtin' => 1
        ),
        'packaging' => array(
            'name' => 'Packaging',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                'tin can',
                'glass jar',
                'paper bag',
                'carton',
                'plastic box',
                'plastic bag'
            )
        ),
        'brand' => array(
            'name' => 'Brand',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true
        ),
        'flavor' => array(
            'name' => 'Flavor',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                'natural',
                'artificial'
            )
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
