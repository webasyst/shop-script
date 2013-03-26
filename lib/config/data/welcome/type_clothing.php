<?php

return array(
    'name' => 'Clothing',
    'icon' => 'ss pt t-shirt',
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
        'size' => array(
            'name' => 'Size',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                '3XS',
                '2XS',
                'XS',
                'S',
                'M',
                'L',
                'XL',
                '2XL',
                '3XL',
                '4XL',
                '5XL',
                '6XL',
                '7XL',
            )
        ),
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
        'age' => array(
            'name' => 'Age',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                'adult',
                'teenager',
                'child'
            )
        )
    )
);