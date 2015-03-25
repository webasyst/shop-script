<?php
$model = new waModel();
$model->exec("CREATE TABLE IF NOT EXISTS `shop_promo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(32) NOT NULL DEFAULT 'link',
  `title` text,
  `body` text,
  `link` text,
  `color` varchar(8) DEFAULT NULL,
  `ext` varchar(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");
$model->exec("CREATE TABLE IF NOT EXISTS `shop_promo_routes` (
  `promo_id` int(10) unsigned NOT NULL,
  `storefront` varchar(255) NOT NULL,
  `sort` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`storefront`,`promo_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");
