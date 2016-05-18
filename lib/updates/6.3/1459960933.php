<?php

$model = new waModel();

$model->exec("
  CREATE TABLE IF NOT EXISTS shop_transfer (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `string_id` varchar(255),
        `create_datetime` datetime,
        `finalize_datetime` datetime,
        `status` enum('sent','completed','cancelled') NOT NULL DEFAULT 'sent',
        `stock_id_from` int(11) NOT NULL,
        `stock_id_to` int(11) NOT NULL, 
        PRIMARY KEY (`id`),
        UNIQUE `string_id` (`string_id`)
  ) ENGINE=MyISAM DEFAULT CHARSET=utf8
");

$model->exec("
  CREATE TABLE IF NOT EXISTS shop_transfer_products (
        `product_id` int(11) NOT NULL,
        `sku_id` int(11) NOT NULL,
        `transfer_id` int(11) NOT NULL, 
        `count` int(11) NOT NULL DEFAULT '0',
        PRIMARY KEY (`product_id`, `sku_id`, `transfer_id`)
  ) ENGINE=MyISAM DEFAULT CHARSET=utf8
");

