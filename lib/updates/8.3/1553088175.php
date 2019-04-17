<?php
$m = new waModel();
try {
    $m->query("SELECT `products_hash` FROM `shop_coupon` WHERE 0");
} catch (waDbException $e) {
    $sql = 'ALTER TABLE `shop_coupon` ADD `products_hash` TEXT NULL';
    $m->exec($sql);
}