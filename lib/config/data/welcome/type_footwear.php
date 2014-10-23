<?php

return array (
    'name' => 'Footwear',
    'icon' => 'ss pt shoe',
    'features' => array(
        'size' => array(
            'name' => 'Size',
            'type' => shopFeatureModel::TYPE_DOUBLE,
            'selectable' => true
        ),
        'color' => array(
            'name' => 'Color',
            'type' => shopFeatureModel::TYPE_COLOR,
            'selectable' => true,
            'multiple' => true,
            'values' => array(
                'black',
                'brown',
                'white',
                'red',
                'blue',
                'green',
                'orange',
                'yellow',
                'gray',
               array('value'=> 'pink','code'=>'#FF1493',),
            ),
        ),
        'top_material' => array(
            'name' => 'Top material',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple' => true,
            'values' => array(
                'leather',
                'cloth',
                'plastic',
                'rubber',
            ),
        ),
        'sole_material' => array(
            'name' => 'Sole material',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                'leather',
                'plastic',
                'rubber',
                'cork',
            ),
        ),
        'brand' => array(
            'name' => 'Brand',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true
        ),
        'country' => array(
            'name' => 'Country of origin',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true
        ),
    ),
);