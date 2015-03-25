<?php
$model = new waModel();
$model->exec("CREATE TABLE IF NOT EXISTS `shop_abtest` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `create_datetime` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");
$model->exec("CREATE TABLE IF NOT EXISTS `shop_abtest_variants` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `abtest_id` int(11) unsigned NOT NULL,
  `code` varchar(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `antest_code` (`abtest_id`,`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");

