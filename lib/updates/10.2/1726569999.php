<?php

$model = new waModel();

try {
    $model->query("SELECT `currency` FROM `shop_transfer` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_transfer` ADD `currency` char(3) DEFAULT NULL");
}

try {
    $model->query("SELECT `price` FROM `shop_transfer_products` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_transfer_products` ADD `price` decimal(15,4) DEFAULT NULL");
}
