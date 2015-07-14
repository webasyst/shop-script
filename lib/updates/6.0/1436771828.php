<?php

$model = new waModel();

$model->exec("CREATE TABLE IF NOT EXISTS `shop_product_og` (
 `product_id` int(11) NOT NULL,
 `property` varchar(255) NOT NULL,
 `content` text NOT NULL,
 PRIMARY KEY (`product_id`, `property`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");

$model->exec("CREATE TABLE IF NOT EXISTS `shop_category_og` (
 `category_id` int(11) NOT NULL,
 `property` varchar(255) NOT NULL,
 `content` text NOT NULL,
 PRIMARY KEY (`category_id`, `property`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");
