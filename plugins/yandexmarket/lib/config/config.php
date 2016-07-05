<?php
/**
 * can be partial override by config at wa-config/apps/shop/plugins/yandexmarket/config.php
 */
return array(
    'currency'                      => array('RUR', 'RUB', 'BYR','BYN', 'KZT', 'UAH', 'USD', 'EUR'),
    'primary_currency'              => array('RUR', 'RUB', 'BYR','BYN', 'KZT', 'UAH',),
    'currency_source'               => 'CB', //it could be one of 'CB', 'CBRF', 'NBU', 'NBK',
    'group_market_category'         => array(
        'Мягкая мебель',
        'Одежда, обувь и аксессуары',
        'Косметика, парфюмерия и уход',
        'Детские товары',
    ),
    'group_market_category_exclude' => array(
        'Детские коляски',
        'Подгузники',
        'Автокресла',
        'Конструкторы',
        'Железные дороги',
        'Трехколесные велосипеды',
        'Кроватки',
    ),
);
