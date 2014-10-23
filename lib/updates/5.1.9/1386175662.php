<?php
$model = new waModel();
$sql = <<<SQL
CREATE TABLE IF NOT EXISTS `shop_feature_values_color` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `feature_id` INT(11) NOT NULL,
  `sort` INT(11) NOT NULL DEFAULT '0',
  `code` MEDIUMINT(8) UNSIGNED DEFAULT NULL,
  `value` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `values` (`feature_id`,`value`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8
SQL;
$model->query($sql);