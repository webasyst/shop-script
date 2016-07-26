<?php
$m = new waModel();
$m->exec("
CREATE TABLE IF NOT EXISTS `shop_api_courier` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `enabled` INT(1) NOT NULL DEFAULT '1',
    `contact_id` INT(11) NULL DEFAULT NULL,
    `create_datetime` DATETIME NOT NULL,
    `orders_processed` INT NOT NULL DEFAULT '0',
    `note` TEXT NULL DEFAULT NULL,
    `api_token` VARCHAR(32) NULL DEFAULT NULL,
    `api_pin` VARCHAR(32) NULL DEFAULT NULL,
    `api_pin_expire` DATETIME NULL DEFAULT NULL,
    `api_last_use` DATETIME NULL DEFAULT NULL,
    `all_storefronts` INT(1) NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
");

$m->exec("
CREATE TABLE IF NOT EXISTS `shop_api_courier_storefronts` (
    `courier_id` INT(11) NOT NULL,
    `storefront` VARCHAR(255) NOT NULL,
    INDEX `courier` (`courier_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
");
