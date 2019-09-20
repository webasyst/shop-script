<?php

$m = new waModel();

try {
    $m->query("SELECT `text_id` FROM `shop_promo` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `shop_promo` ADD `text_id` varchar(64) NULL");
    $m->exec("ALTER TABLE `shop_promo` ADD `note` text NULL");
    $m->exec("ALTER TABLE `shop_promo` ADD `start_datetime` datetime NULL");
    $m->exec("ALTER TABLE `shop_promo` ADD `finish_datetime` datetime NULL");
    $m->exec("ALTER TABLE `shop_promo` ADD `author_contact_id` int(11) NULL");
    $m->exec("ALTER TABLE `shop_promo` ADD `create_datetime` datetime NULL");
    $m->exec("ALTER TABLE `shop_promo` ADD `update_datetime` datetime NULL");
    $m->exec("ALTER TABLE `shop_promo` ADD `consider_end_orders` tinyint(1) DEFAULT 1 NOT NULL");
    $m->exec("ALTER TABLE `shop_promo` MODIFY COLUMN `consider_end_orders` tinyint(1) AFTER `enabled`");
}