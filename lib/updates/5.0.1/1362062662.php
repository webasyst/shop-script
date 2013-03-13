<?php

$mod = new waModel();
$mod->exec('
  CREATE TABLE IF NOT EXISTS `shop_affiliate_transaction` (
   `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
   `contact_id` int(11) UNSIGNED NOT NULL,
   `create_datetime` datetime NOT NULL,
   `order_id` int(11) UNSIGNED DEFAULT NULL,
   `amount` decimal(15,4) NOT NULL,
   `balance_after` decimal(15,4) NOT NULL,
   `comment` text,
   PRIMARY KEY (`id`),
   KEY `contact_id` (`contact_id`)
  ) ENGINE=MyISAM DEFAULT CHARSET=utf8
');

