<?php
$m = new waModel();
try {
    $m->query("SELECT `tax_percent` FROM `shop_order_items` WHERE 0");
    $m->query("ALTER TABLE `shop_order_items` ALTER COLUMN `tax_percent` SET DEFAULT NULL");
} catch (waDbException $e) {
    $sql = 'ALTER TABLE `shop_order_items` ADD `tax_percent` decimal(7,4) DEFAULT NULL NULL';
    $m->exec($sql);
}
try {
    $m->query("SELECT `tax_included` FROM `shop_order_items` WHERE 0");
    $m->query("UPDATE `shop_order_items` SET `tax_included`=IF(`tax_included`, 1, 0)");
    $m->query("ALTER TABLE `shop_order_items` MODIFY `tax_included` int(1) NOT NULL DEFAULT '0'");
} catch (waDbException $e) {
    $sql = "ALTER TABLE `shop_order_items` ADD `tax_included` int(1) DEFAULT '0'";
    $m->exec($sql);
}
