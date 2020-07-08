<?php

return array(
    'name'     => 'Default product type',
    'icon'     => 'box',
    'features' => array(
        'weight' => array(
            'name' => 'Weight',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.weight',
            'builtin' => 1
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
                'blue',
                'brown',
                'red',
                'orange',
                'silver',
                'gray',
                'black',
            )
        ),
        'gtin' => array(
            'name' => 'GTIN',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'status' => shopFeatureModel::STATUS_PRIVATE,
            'available_for_sku' => 1,
            'builtin' => 1
        ),
    ),
);
