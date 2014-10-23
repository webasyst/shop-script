<?php

$model = new waModel();
$sql = "CREATE TABLE IF NOT EXISTS `shop_checkout_flow` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(32) NULL DEFAULT NULL,
    `contact_id` INT(11) NULL DEFAULT NULL,
    `date` DATE NULL DEFAULT NULL,
    `year` SMALLINT(6) DEFAULT NULL,
    `quarter` SMALLINT(6) DEFAULT NULL,
    `month` SMALLINT(6) DEFAULT NULL,
    `step` TINYINT(2) NOT NULL DEFAULT 0,
    `description` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";
$model->exec($sql);
