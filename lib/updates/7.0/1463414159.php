<?php
$m = new waModel();
//$m->exec("DROP TABLE IF EXISTS `shop_stock_rules`");
$m->exec("
    CREATE TABLE IF NOT EXISTS `shop_stock_rules` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `sort` int(11) NOT NULL DEFAULT '0',
        `stock_id` int(11) DEFAULT NULL,
        `virtualstock_id` int(11) DEFAULT NULL,
        `rule_type` varchar(255) NOT NULL,
        `rule_data` text,
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8
");
