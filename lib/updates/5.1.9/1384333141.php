<?php
$sql = <<<SQL
CREATE TABLE IF NOT EXISTS `shop_importexport` (
`id` INT(11) NOT NULL AUTO_INCREMENT,
  `plugin` VARCHAR(64) NOT NULL,
  `sort` INT(11) NOT NULL DEFAULT '0',
  `name` VARCHAR(255) DEFAULT NULL,
  `description` TEXT NULL DEFAULT NULL ,
  `config` TEXT NULL DEFAULT NULL ,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`plugin`,`id`,`sort`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8
SQL;

$model = new waModel();
$model->exec($sql);