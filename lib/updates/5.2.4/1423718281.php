<?php
$model = new waModel();
$model->exec("CREATE TABLE IF NOT EXISTS `shop_expense` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `type` varchar(16) NOT NULL,
    `name` varchar(255) NOT NULL,
    `storefront` varchar(255) DEFAULT NULL,
    `start` date NOT NULL,
    `end` date NOT NULL,
    `amount` decimal(15,4) NOT NULL,
    `color` varchar(7) DEFAULT NULL,
    `note` text,
    PRIMARY KEY (`id`),
    INDEX `start_end` (`start`, `end`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8");

