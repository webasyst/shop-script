<?php

return array(
    'name' => 'Sports',
    'icon' => 'ss pt sports',
    'features' => array(
        'sex' => array(
            'name' => 'Sex',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                'men',
                'women',
                'unisex',
            ),
        ),
        'size' => array(
            'name' => 'Size',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true
        ),
        'weight' => array(
            'name' => 'Weight',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.weight',
            'available_for_sku' => 1,
            'builtin' => 1
        ),
        'level' => array(
            'name' => 'Level',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                'beginner',
                'advanced',
                'professional'
            )
        ),
        'max_user_weight' => array(
            'name' => 'Maximum user weight',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.weight'
        ),
        'brand' => array(
            'name' => 'Brand',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true
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
