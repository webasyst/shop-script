<?php

$m = new waModel();

try {
    $m->exec("SELECT transfer_id FROM `shop_product_stocks_log` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `shop_product_stocks_log` ADD COLUMN transfer_id INT(11) NULL DEFAULT NULL");
}