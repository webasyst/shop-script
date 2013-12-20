<?php

return array(
    'name'     => 'Default product type',
    'icon'     => 'box',
    'features' => array(
        'weight' => array(
            'name' => 'Weight',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.weight'
        ),
        'length' => array(
            'name' => 'Length',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.length',
        ),
        'color'  => array(
            'name'       => 'Color',
            'type'       => shopFeatureModel::TYPE_COLOR,
            'selectable' => true,
            'multiple'   => true,
            'values'     => array(
                'white',
                'yellow',
                'green',
                'brown',
                'red',
                'orange',
                'silver',
                'gray',
                'black',
            )
        ),
    ),
);