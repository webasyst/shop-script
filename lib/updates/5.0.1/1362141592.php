<?php

$mod = new waModel();
$mod->exec("
    CREATE TABLE IF NOT EXISTS `shop_followup` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
        `delay` INT UNSIGNED NOT NULL,
        `first_order_only` TINYINT UNSIGNED NOT NULL DEFAULT '1',
        `subject` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
        `body` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
        `last_cron_time` DATETIME NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE = MYISAM
");

