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
            'selecable' => true,
            'multiple' => true
        ),
    ),
);