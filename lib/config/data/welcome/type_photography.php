<?php
return array(
    'name'     => 'Photography',
    'icon'     => 'ss pt camera',
    'features' => array(

        'brand' => array(
            'name' => 'Brand',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true
        ),
        'screen_size'  => array(
            'name'       => 'Screen size',
            'type'       => shopFeatureModel::TYPE_DIMENSION.'.length',
            'selectable' => true,
            'multiple'   => false,
            'values'     => array(
                array(
                    'value' => 3,
                    'unit'  => 'in',
                ),
                array(
                    'value' => 4,
                    'unit'  => 'in',
                ),
            ),
        ),
        'iso'          => array(
            'name'       => 'ISO',
            'type'       => shopFeatureModel::TYPE_DOUBLE,
            'selectable' => true,
            'multiple'   => true,
            'values'     => array(50, 100, 200, 400, 600, 800, 1600, 3200, 6400, 12800),
        ),
        'aperture'     => array(
            'name'       => 'Aperture',
            'type'       => shopFeatureModel::TYPE_DOUBLE,
            'selectable' => true,
            'multiple'   => false,
            'values'     => array(),
        ),
        'bayonet'      => array(
            'name'       => 'Bayonet',
            'type'       => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple'   => false,
            'values'     => array(
                'Canon EF',
                'Canon EF-M',
                'Canon EF/EF-S',
                'Fujifilm X Mount',
                'Hasselblad HC',
                'Leica M',
                'Leica S',
                'Micro Four Thirds',
                'Minolta A',
                'Nikon 1',
                'Nikon F',
                'Pentax KA/KAF/KAF2',
                'Pentax Q',
                'Samsung NX',
                'Sigma SA',
                'Sony E',
                'Zuiko Digital',
            ),
        ),
        'weight' => array(
            'name' => 'Packaging weight',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.weight'
        ),
    ),
);
