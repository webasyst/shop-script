<?php

return array(
    'name' => 'Video',
    'icon' => 'ss pt tv',
    'features' => array(
        'display_type' => array(
            'name' => 'Display type',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                'LED',
                'LCD',
                'plasma',
                'projection',
            ),
        ),
        'screen_size' => array(
            'name' => 'Screen size',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.length',
            'selectable' => true,
        ),
        'brand' => array(
            'name' => 'Brand',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
        ),
        'resolution' => array(
            'name' => 'Screen resolution',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                '1024 x 768',
                '1366 x 768',
                '1920 x 1080',
            ),
        ),
        'brightness' => array(
            'name' => 'Brightness',
            'type' => shopFeatureModel::TYPE_DOUBLE,
        ),
        'refresh_rate' => array(
            'name' => 'Refresh rate',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.frequency',
        ),
        'power_consumption' => array(
            'name' => 'Power consumption',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.power',
        )
    ),
);