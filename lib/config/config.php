<?php

/**
 * Don't change this file!
 * If you want to change some settings you can create file wa-config/apps/shop/config.php
 * (this file will not be restored to default after updates) and make all changes in it
 *
 * For example,
 *
 * wa-config/apps/shop/config.php
 * <?php
 *
 * return array(
 *     'products_per_page' => 100,
 *     'can_use_smarty' => true,
 * );
 */
return array(
    'products_per_page'        => 30,
    'reviews_per_page_total'   => 30,
    'reviews_per_page_product' => 5,
    'review_highlight_time'    => 180,
    'products_default_view'    => 'thumbs',/* 'table' or 'thumbs' */
    'orders_default_view'      => 'split',
    'orders_per_page'          => array('split' => 15, 'table' => 30),
    'types_per_page'           => 25,
    'features_per_page'        =>200,
    'orders_update_list'       => 60000, // miliseconds

    'stocks_log_items_per_page' => 30,
    
    'image_sizes'              => array(),
    'image_max_size'           => 1000,
    'image_sharpen'            => 1,
    'image_thumbs_on_demand'   => 1,
    'image_save_original'      => 1,
    'image_save_quality'       => 90,

    'enable_2x'                => 1,
    'image_save_quality_2x'    => 70,

    // search weights
    'search_weights' => array(
        'name' => 90,
        'summary' => 20,
        'description' => 20,
        'tag' => 30,
        'feature' => 30,
        'sku' => 40,
        'other' => 10,
    ),

    'search_ignore' => ',.!?«»"\'()[]<>|/',

    /**
     * the ability to use Smarty within product/category description
     */
    'can_use_smarty' => false,

    /**
     * list of available order state icons (CSS classes)
     */
    'order_state_icons' => array(
        'new',
        'processing',
        'paid',
        'sent',
        'completed',
        'refunded',
        'trash',
        'flag-white',
        'flag-blue',
        'flag-yellow',
        'flag-green',
        'flag-red',
        'flag-purple',
        'flag-black',
        'flag-checkers'
    ),
    /**
     * list of available product type icons (CSS classes)
     */
    'type_icons'               => array(
        'ss pt box',    
        'ss pt sports',
        'ss pt soccer',
        'ss pt cake',
        'ss pt music',
        'ss pt car',
        'ss pt diamond',
        'ss pt audio',
        'ss pt globe',
        'ss pt mobile',
        'ss pt gingerbread-man',
        'ss pt camera',
        'ss pt rocket',
        'ss pt plane',
        'ss pt shoe',
        'ss pt gift',
        'ss pt nuclear',
        'ss pt map',

        'ss pt tv',
        'ss pt t-shirt',
        'ss pt disc',
        'ss pt computer',
        'ss pt building',
        'ss pt toolbox',
        'ss pt hamburger',
        'ss pt cookie',
        'ss pt door',
        'ss pt clapperboard',
        'ss pt traffic-cone',
        'ss pt hammer',
        'ss pt guitar',
        'ss pt disk',
        'ss pt cup',
        'ss pt auction-hammer',
        'ss pt drawer',
        'ss pt traffic-light',

        'ss pt clock',
        'ss pt chair',
        'ss pt downloadable',
        'ss pt sunglasses',
        'ss pt umbrella',
        'ss pt controller',
        'ss pt key',
        'ss pt sushi',
        'ss pt cat',
        'ss pt monkey',
        'ss pt socket',
        'ss pt spray',
    ),
);
