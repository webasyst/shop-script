<?php

return array(
    'name' => 'Toys',
    'icon' => 'ss pt cat',
    'features' => array(
        'brand' => array(
            'name' => 'Brand',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
        ),
        'type' => array(
            'name'   => 'Type',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                'toy weapon',
                'constructor',
                'cubes',
                'doll',
                'car',
                'soft toy',
                'board game',
                'rattle',
                'role-playing game',
                'statuette',
                'household appliance',
                'kite',
                'swimming accessories',
                'railroad',
                'game mat',
                'game console',
                'tool',
                'books',
                'doll stroller',
                'doll furniture',
                'doll house',
                'mobile',
                'puzzle',
                'musical instrument',
                'roly-poly',
                'doll dishes',
                'products',
                'teether',
                'robots',
                'sports accessories',
                'soldiers',
                'sorter',
                'buckets & spades',
                'frisbee',
            ),
        ),
        'material' => array(
            'name' => 'Material',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple' => true,
            'values' => array(
                'metal',
                'plastic',
                'wood',
                'cloth',
                'leather',
                'paper',
                'porcelain',
            ),
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
    ),
);
