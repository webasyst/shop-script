<?php

$model = new waModel();
try {
    $model->query("SELECT sku_code FROM shop_order_items WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE  `shop_order_items` ADD  `sku_code` VARCHAR( 255 ) NOT NULL DEFAULT  '' AFTER  `sku_id`");
}
