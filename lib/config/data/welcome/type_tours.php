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
        )
    )
);