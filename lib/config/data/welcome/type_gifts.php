<?php

return array(
    'name' => 'Gifts & souvenirs',
    'icon' => 'ss pt gift',
    'features' => array(
        'for_whom' => array(
            'name' => 'For whom',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple' => true,
            'values' => array(
                'for him',
                'for her',
                'father',
                'mother',
                'sister',
                'brother',
                'husband',
                'wife',
                'son',
                'daughter',
                'male friend',
                'female friend',
                'grandfather',
                'grandmother',
                'boss',
                'colleague',
            )
        ),
        'occasion' => array(
            'name' => 'Occasion',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple' => true,
            'values' => array(
                'birthday',
                'Christmas & New Year',
                'wedding',
                'new home',
                'Valintine’s day',
                'engagement',
                'retirement',
                'thank you',
                'mother’s day',
                'father’s day',
                'Easter',
                'graduation'
            )
        ),
        'type' => array(
            'name' => 'Type',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                'gadget',
                'souvenir',
                'entertainment',
                'sports',
                'office',
                'beauty',
                'home',
                'tourism',
                'automobile'
            )
        ),
        'weight' => array(
            'name' => 'Weight',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.weight'
        ),
    )
);