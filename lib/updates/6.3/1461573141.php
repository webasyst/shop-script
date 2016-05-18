<?php
$m = new waModel();
$m->exec("
    CREATE TABLE IF NOT EXISTS `shop_virtualstock` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `low_count` int(11) NOT NULL DEFAULT '0',
        `critical_count` int(11) NOT NULL DEFAULT '0',
        `sort` int(11) NOT NULL DEFAULT '0',
        `name` varchar(255) DEFAULT NULL,
        `public` int(1) NOT NULL DEFAULT '1',
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8
");
$m->exec("
    CREATE TABLE IF NOT EXISTS `shop_virtualstock_stocks` (
        `virtualstock_id` int(11) NOT NULL,
        `stock_id` int(11) NOT NULL,
        `sort` int(11) NOT NULL
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8
");
