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
    'product_orders_per_page' => 30,
    'types_per_page'           => 25,
    'features_per_page'        => 200,
    'statrows_per_page'        => 50,
    'orders_update_list'       => 60000, // miliseconds

    'stocks_log_items_per_page' => 30,
    'marketing_expenses_per_page' => 25,
    'customers_per_page' => 30,

    'image_sizes'              => array(
        '0x320'
    ),
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
    'search_smart' => true,

    /**
     * the ability to use Smarty within product/category description
     */
    'can_use_smarty' => false,
    'rounding_options' => array(
        '100' => /*_w*/("Round to nearest 100"),
        '99' => /*_w*/("Round to nearest 99"),
        '10' => /*_w*/("Round to nearest 10"),
        '1' => /*_w*/("Round to nearest 1.00"),
        '0.99' => /*_w*/("Round to nearest .99"),
        '0.1' => /*_w*/("Round to nearest 0.1"),
        '' => /*_w*/("Round to nearest 0.01"),
    ),

    /**
     * List of domains for the Social section in Sales Report
     */
    'social_domains' => array(
        'vk.com' => array(
            'name' => /*_w(*/'ВКонтакте'/*)*/,
            'icon_class' => 'vkontakte',
        ),
        'www.facebook.com' => array(
            'name' => /*_w(*/'Facebook'/*)*/,
            'icon_class' => 'facebook',
        ),
        'twitter.com' => array(
            'name' => /*_w(*/'Twitter'/*)*/,
            'icon_class' => 'twitter',
        ),
        'ok.ru' => array(
            'name' => /*_w(*/'Одноклассники'/*)*/,
            'icon_class' => '',
        ),
        'plus.google.com' => array(
            'name' => /*_w(*/'Google+'/*)*/,
            'icon_class' => 'google',
        ),
        'instagram.com' => array(
            'name' => /*_w(*/'Instagram'/*)*/,
            'icon_class' => '',
        ),
        'www.linkedin.com' => array(
            'name' => /*_w(*/'LinkedIn'/*)*/,
            'icon_class' => 'linkedin',
        ),
        'my.mail.ru' => array(
            'name' => /*_w(*/'Мой Мир@Mail.Ru'/*)*/,
            'icon_class' => '',
        ),
        'myspace.com' => array(
            'name' => /*_w(*/'Myspace'/*)*/,
            'icon_class' => '',
        ),
        'ru.foursquare.com' => array(
            'name' => /*_w(*/'Foursquare'/*)*/,
            'icon_class' => '',
        ),
        'foursquare.com' => array(
            'name' => /*_w(*/'Foursquare'/*)*/,
            'icon_class' => '',
        ),
        'www.deviantart.com' => array(
            'name' => /*_w(*/'DeviantArt'/*)*/,
            'icon_class' => '',
        ),
        'www.pinterest.com' => array(
            'name' => /*_w(*/'Pinterest'/*)*/,
            'icon_class' => '',
        ),
        'www.tumblr.com' => array(
            'name' => /*_w(*/'Tumblr'/*)*/,
            'icon_class' => '',
        ),
    ),

    /**
     * Default colors for popular traffic sources in Marketing Costs editor
     */
    'traffic_sources' => array(
        'google.com' => array(
            'color' => '#2171eb',
        ),
        'google.ru' => array(
            'color' => '#2171eb',
        ),
        'google.co.uk' => array(
            'color' => '#2171eb',
        ),
        'google.ca' => array(
            'color' => '#2171eb',
        ),
        'google.com.au' => array(
            'color' => '#2171eb',
        ),
        'google.co.jp' => array(
            'color' => '#2171eb',
        ),
        'google.de' => array(
            'color' => '#2171eb',
        ),
        'google.es' => array(
            'color' => '#2171eb',
        ),
        'google.fr' => array(
            'color' => '#2171eb',
        ),
        'google.it' => array(
            'color' => '#2171eb',
        ),
        'www.facebook.com' => array(
            'color' => '#3b5998',
        ),
        'twitter.com' => array(
            'color' => '#5ea9dd',
        ),
        'www.bing.com' => array(
            'color' => '#ffb900',
        ),
        'yandex.ru' => array(
            'color' => '#fc0d1b',
        ),
        'vk.com' => array(
            'color' => '#5b7c9f',
        ),
    ),

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
     * list of available order action icons (CSS classes)
     */
    'order_action_icons' => array(
        'ss new',
        'ss processing',
        'ss paid',
        'ss sent',
        'ss completed',
        'ss refunded',
        'ss trash',
        'ss flag-white',
        'ss flag-blue',
        'ss flag-yellow',
        'ss flag-green',
        'ss flag-red',
        'ss flag-purple',
        'ss flag-black',
        'ss flag-checkers'
    ),

    'customers_filter_icons' => array(
        'funnel',
        'star',
        'bug',
        'lightning',
        'light-bulb',
        'comments',
        'lock',
        'lock-unlocked',
        'broom',
        'contact',
        'reports',
        'books',
        'marker',
        'lens',
        'alarm-clock',
        'notebook',
        'blog',
        'car',
        'disk',
        'cookie',
        'burn',
        'clapperboard',
        'cup',
        'home',
        'smiley',
        'medal',
        'target',
        'store'
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
