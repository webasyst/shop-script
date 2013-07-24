<?php

$model = new waModel();

$model->exec("CREATE TABLE IF NOT EXISTS `shop_category_routes` (
  `category_id` int(11) NOT NULL,
  `route` varchar(255) NOT NULL,
  PRIMARY KEY (`category_id`,`route`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");

try {
    $model->exec("INSERT IGNORE INTO shop_category_routes (category_id, route) SELECT id, route FROM shop_category WHERE route IS NOT NULL");
    $model->exec("ALTER TABLE shop_category DROP route");
} catch (waDbException $e) {
}