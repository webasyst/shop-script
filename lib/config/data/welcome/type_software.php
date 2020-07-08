<?php
return array(
    'name'     => 'Software',
    'icon'     => 'ss pt disc',
    'features' => array(
        'license' => array(
            'name'   => 'License',
            'type'   => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'values' => array('GPL', 'GPL v1', 'GPL v2', 'GPL v3', 'LGPL', 'BSD', 'MIT')
        ),
        'os'      => array(
            'name'   => 'Operating system',
            'type'   => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true
        ),
        'localization' => array(
            'name' => 'Localization languages',
            'type' => shopFeatureModel::TYPE_VARCHAR,
            'selectable' => true,
            'multiple' => true
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
