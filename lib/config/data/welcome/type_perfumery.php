<?php

return array(
    'name' => 'Perfumes',
    'icon' => 'ss pt spray',
    'features' => array(
        'brand' => array(
            'name' => 'Brand',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                'Armani',
                'Calvin Klein',
                'Carolina Herrera',
                'Chanel',
                'Christian Dior',
                'Davidoff',
                'Estee Lauder',
                'Givenchy',
                'Gucci',
                'Guerlain',
                'HUGO BOSS',
                'Kenzo',
                'Lacoste',
                'Lancome',
                'Montale',
                'Nina Ricci',
                'Yves Saint Laurent',
            ),
        ),
        'type' => array(
            'name' => 'Type',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                'perfume',
                'toilet water',
                'eau de parfum',
                'eau de cologne',
            ),
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
        'weight' => array(
            'name' => 'Weight',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.weight'
        ),
    ),
);