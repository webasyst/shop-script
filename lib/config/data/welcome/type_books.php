<?php

return array(
    'name' => 'Books',
    'icon' => 'ss pt books',
    'features' => array(
        'author' => array(
            'name' => 'Author',
            'type' => shopFeatureModel::TYPE_VARCHAR,
        ),
        'publisher' => array(
            'name' => 'Publisher',
            'type' => shopFeatureModel::TYPE_VARCHAR,
        ),
        'imprint_date' => array(
            'name' => 'Imprint date',
            'type' => shopFeatureModel::TYPE_DOUBLE,
        ),
        'language' => array(
            'name' => 'Language',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(),
        ),
        'page_count' => array(
            'name' => 'Page count',
            'type' => shopFeatureModel::TYPE_DOUBLE,
        ),
        'page_format' => array(
            'name' => 'Page format',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(),
        ),
        'genre' => array(
            'name' => 'Genre',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple' => true,
            'values' => array(
                'Drama',
                'Fable',
                'Fairy tale',
                'Fantasy',
                'Folklore',
                'Historical fiction',
                'Horror',
                'Humor',
                'Mythology',
                'Poetry',
                'Science fiction',
                'Biography',
            ),
        ),
        'illustrations' => array(
            'name' => 'Illustrations',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple' => true,
            'values' => array(
                'colored',
                'black & white',
                'none',
            ),
        ),
    ),
);