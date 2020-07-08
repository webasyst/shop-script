<?php

return array(
    'name' => 'Tours',
    'icon' => 'ss pt sunglasses',
    'features' => array(
        'countries' => array(
            'name' => 'Countries',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple' => true
        ),
        'locations' => array(
            'name' => 'Locations',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple' => true
        ),
        'start_date' => array(
            'name' => 'Start date',
            'type' => shopFeatureModel::TYPE_VARCHAR
        ),
        'duration' => array(
            'name' => 'Duration',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.time'
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
