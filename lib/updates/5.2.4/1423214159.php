<?php
$model = new waModel();
try {
    $model->query("SELECT hash FROM shop_sales WHERE 0");
} catch (waDbException $e) {
    $model->exec("CREATE TABLE IF NOT EXISTS `shop_sales` (
         `hash` varchar(32) NOT NULL,
         `date` date NOT NULL,
         `name` varchar(255) NOT NULL DEFAULT '',
         `order_count` int(11) NOT NULL DEFAULT '0',
         `sales` float NOT NULL DEFAULT '0',
         `shipping` float NOT NULL DEFAULT '0',
         `tax` float NOT NULL DEFAULT '0',
         `purchase` float NOT NULL DEFAULT '0',
         `cost` float NOT NULL DEFAULT '0',
         `new_customer_count` INT(11) NOT NULL DEFAULT '0',
         PRIMARY KEY (`hash`,`name`,`date`),
         KEY `date` (`date`),
         KEY `hash_date` (`hash`, `date`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8");
}

