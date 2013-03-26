<?php

return array(
    'name' => 'Real estate',
    'icon' => 'ss pt building',
    'features' => array(
        'bedrooms' => array(
            'name' => 'Bedrooms',
            'type' => shopFeatureModel::TYPE_DOUBLE,
            'selectable' => true,
            'values' => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10)
        ),
        'baths' => array(
            'name' => 'Baths',
            'type' => shopFeatureModel::TYPE_DOUBLE,
            'selectable' => true,
            'values' => array(1, 2, 3, 4)
        ),
        'year' => array(
            'name' => 'Year built',
            'type' => shopFeatureModel::TYPE_DOUBLE
        ),
        'living_area' => array(
            'name' => 'Square feet',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.area'
        ),
        'lot' => array(
            'name' => 'Lot size',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.area'
        ),
        'stories' => array(
            'name' => 'Stories',
            'type' => shopFeatureModel::TYPE_DOUBLE,
            'selectable' => true,
            'values' => array(1, 2, 3, 4)
        ),
        'construction' => array(
            'name' => 'Construction',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
        ),
        'utilities' => array(
            'name' => 'Utilities',
            'type' => shopFeatureModel::TYPE_VARCHAR
        ),
        'address' => array(
            'name' => 'Address',
            'type' => shopFeatureModel::TYPE_VARCHAR
        ),
    ),

);