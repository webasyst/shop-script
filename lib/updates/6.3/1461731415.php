<?php
$m = new waModel();
try {
    $m->exec("ALTER TABLE `shop_order_items` ADD `virtualstock_id` INT(11) NULL DEFAULT NULL AFTER `stock_id`");
} catch (waDbException $e) {
}