<?php
$m = new waModel();

try {
    $m->query("SELECT `state_id` FROM `shop_followup` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `shop_followup` ADD `state_id` varchar(32) NOT NULL DEFAULT 'paid'");
    $m->exec("UPDATE `shop_followup` SET `state_id` = 'paid'");
}

try {
    $m->exec("ALTER TABLE `shop_order_log` CHANGE `before_state_id` `before_state_id` VARCHAR (32) NOT NULL");
    $m->exec("ALTER TABLE `shop_order_log` CHANGE `after_state_id` `after_state_id` VARCHAR (32) NOT NULL");
    $m->exec("ALTER TABLE `shop_order_log` ADD INDEX `datetime` (`datetime`)");
} catch (waDbException $e) {
}
