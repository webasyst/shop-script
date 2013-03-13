<?php

$model = new waModel();

try {
    $model->query("SELECT stock_id FROM `shop_order_items` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_order_items` ADD stock_id INT(11) NULL");
}

try {
    $model->query("SELECT purchase_price FROM `shop_order_items` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_order_items` ADD purchase_price decimal(15,4) NOT NULL DEFAULT 0");
}