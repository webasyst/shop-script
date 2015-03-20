<?php

// create new tables for customers filters
$model = new waModel();
$model->exec("CREATE TABLE IF NOT EXISTS `shop_customers_filter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `hash` TEXT,
  `create_datetime` DATETIME NULL,
  `contact_id` INT(11) NOT NULL,
  `icon` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  KEY `contact_id` (`contact_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8");
