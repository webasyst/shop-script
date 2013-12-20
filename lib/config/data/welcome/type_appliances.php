<?php

return array(
    'name' => 'Appliances',
    'icon' => 'ss pt socket',
    'features' => array(
        'brand' => array(
            'name' => 'Brand',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true
        ),
        'power_source' => array(
            'name' => 'Power source',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple' => true,
            'values' => array(
                'car cigarette lighter',
                'battery',
                'wall outlet',
            )
        ),
        'power_consumption' => array(
            'name' => 'Power consumption',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.power'
        ),
        'cord_length' => array(
            'name' => 'Power cord length',
            'type' => shopFeatureModel::TYPE_DIMENSION.'.length',
        ),
        'construction' => array(
            'name' => 'Construction',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                'stand-alone',
                'built-in'
            )
        ),
        'control_type' => array(
            'name' => 'Control type',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array(
                'mechanical',
                'electronic'
            )
        ),
        'color' => array(
            'name' => 'Color',
            'type' => shopFeatureModel::TYPE_COLOR,
            'selectable' => true,
            'multiple' => true,
            'values' => array(
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
        'weight'=>array(
            'name'=>'Weight',
            'type'=>shopFeatureModel::TYPE_DIMENSION.'.weight',
        )

    )
);