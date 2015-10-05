<?php
return array(
    'metric' => array(
        'title' => /*_wp*/('Metric'),
        'description' => /*_wp*/(''),
        'value' => 'sales',
        'control_type' => waHtmlControl::RADIOGROUP,
        'options' => array(
            array('value' => 'sales', 'title' => /*_wp*/('Sales')),
            array('value' => 'profit', 'title' => /*_wp*/('Profit'))
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
    'compare' => array(
        'title' => /*_wp*/('Compare to'),
        'description' => /*_wp*/(''),
        'value' => 'previous',
        'control_type' => waHtmlControl::SELECT,
        'options' => array(
            array('value' => '', 'title' => /*_wp*/('Disabled')),
            array('value' => 'previous', 'title' => /*_wp*/('Previous period')),
            array('value' => 'year_ago', 'title' => /*_wp*/('Same period one year ago')),
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