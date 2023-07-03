<?php
return array(
    'metric' => array(
        'title' => /*_wp*/('Metric'),
        'description' => /*_wp*/(''),
        'value' => 'sales',
        'control_type' => waHtmlControl::RADIOGROUP,
        'options' => array(
            array('value' => 'sales', 'title' => /*_wp*/('Sales')),
            array('value' => 'profit', 'title' => /*_wp*/('Profit')),
            array('value' => 'order_count', 'title' => /*_wp*/('Number of paid orders')),
        ),
    ),
    'period' => array(
        'title' => /*_wp*/('Period'),
        'description' => /*_wp*/(''),
        'value' => 30*24*3600,
        'control_type' => waHtmlControl::SELECT,
        'options' => array(
            array('value' => 7*24*3600, 'title' => /*_wp*/('Last 7 days')),
            array('value' => 30*24*3600, 'title' => /*_wp*/('Last 30 days')),
            array('value' => 90*24*3600, 'title' => /*_wp*/('Last 90 days')),
        ),
    ),
    'storefront' => array(
        'title' => /*_wp*/('Storefront'),
        'description' => /*_wp*/(''),
        'value' => '',
        'control_type' => waHtmlControl::SELECT,
        'options' => array(
            array('value' => '', 'title' => /*_wp*/('All storefronts')),
            // Rest added by getSettingsConfig()
        ),
    ),
);