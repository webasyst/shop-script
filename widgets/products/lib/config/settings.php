<?php
return array(
    'list_type' => array(
        'title' => /*_wp*/('Product list'),
        'description' => /*_wp*/(''),
        'value' => 'bestsellers30',
        'control_type' => waHtmlControl::RADIOGROUP,
        'options' => array(
            array('value' => 'bestsellers7', 'title' => /*_wp*/('Last 7 days bestsellers')),
            array('value' => 'bestsellers30', 'title' => /*_wp*/('Last 30 days bestsellers')),
            array('value' => 'bestsellers90', 'title' => /*_wp*/('Last 90 days bestsellers')),
            array('value' => 'out_of_stock', 'title' => /*_wp*/('Out of stock')),
            array('value' => 'low_stock', 'title' => /*_wp*/('Running out of stock')),
            array('value' => 'last_sold', 'title' => /*_wp*/('Last ordered')),
            array('value' => 'stock', 'title' => /*_wp*/('In-stock assets')),
        ),
    ),
);