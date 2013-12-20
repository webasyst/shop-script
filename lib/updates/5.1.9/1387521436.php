<?php

$model = new waModel();
$sql = "CREATE TABLE IF NOT EXISTS `shop_product_stocks_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `sku_id` INT(11) NOT NULL,
    `stock_id` INT(11) NULL DEFAULT NULL,
    `stock_name` VARCHAR(255) NULL DEFAULT NULL,
    `before_count` INT(11) NULL DEFAULT NULL,
    `after_count` INT(11) NULL DEFAULT NULL,
    `diff_count` INT(11) NULL DEFAULT NULL,
    `type` VARCHAR(32) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `datetime` DATETIME NOT NULL,
    `order_id` INT(11) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `product_id` (`product_id`, `sku_id`),
    KEY `stock_id` (`stock_id`)
)  ENGINE=MyISAM DEFAULT CHARSET=utf8";
$model->exec($sql);
