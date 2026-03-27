<?php
$m = new waModel();
$m->exec("
    CREATE TABLE IF NOT EXISTS `shop_order_assign_rules` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `sort` int(11) NOT NULL DEFAULT '0',
        `action_id` varchar(255) NOT NULL,
        `conditions` text,
        `rule_data` text,
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8
");
