<?php
// Shipping date column
$m = new waModel();
try {
    $m->query("SELECT `shipping_datetime` FROM `shop_order` WHERE 0");
} catch (waDbException $e) {
    $m->exec(
        "ALTER TABLE `shop_order`
        ADD `shipping_datetime` DATETIME NULL DEFAULT NULL,
        ADD INDEX `shipping_datetime` (`shipping_datetime`)"
    );
}

try {
    $m->query("SELECT `tax_percent` FROM `shop_order_items` WHERE 0");
} catch (waDbException $e) {
    $sql = 'ALTER TABLE `shop_order_items` ADD `tax_percent` decimal(7,4) DEFAULT NULL NULL';
    $m->exec($sql);
}
try {
    $m->query("SELECT `tax_included` FROM `shop_order_items` WHERE 0");
} catch (waDbException $e) {
    $sql = 'ALTER TABLE `shop_order_items` ADD `tax_included` decimal DEFAULT 0';
    $m->exec($sql);
}
$sqls = array(
    "ALTER TABLE `shop_product` MODIFY `image_filename` varchar(255) NOT NULL DEFAULT ''",
    "ALTER TABLE `shop_product` MODIFY `video_url` varchar(255) DEFAULT NULL NULL",
    "ALTER TABLE `shop_product_images` MODIFY `filename` varchar(255) NOT NULL DEFAULT ''",
);
foreach ($sqls as $sql) {
    $m->exec($sql);
}
