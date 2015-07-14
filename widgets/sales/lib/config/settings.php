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
);