<?php
return array(
//    'metric' => array(
//        'title' => /*_wp*/('Metric'),
//        'description' => /*_wp*/(''),
//        'value' => 'total',
//        'control_type' => waHtmlControl::RADIOGROUP,
//        'options' => array(
//            array('value' => 'total', 'title' => /*_wp*/('Total number of customers')),
//            array('value' => 'newret', 'title' => /*_wp*/('New vs Returning')),
//        ),
//    ),
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
);