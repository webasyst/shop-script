<?php

$model = new waModel();

$sql = "ALTER TABLE `shop_product` MODIFY COLUMN `count` DECIMAL(15,3)";
$model->exec($sql);

$sql = "ALTER TABLE `shop_order_items` MODIFY COLUMN `quantity` DECIMAL(15,3) NOT NULL";
$model->exec($sql);

$sql = "ALTER TABLE `shop_product_skus` MODIFY COLUMN `count` DECIMAL(15,3)";
$model->exec($sql);

$sql = "ALTER TABLE `shop_product_stocks` MODIFY COLUMN `count` DECIMAL(15,3) NOT NULL";
$model->exec($sql);

$sql = "ALTER TABLE `shop_transfer_products` MODIFY COLUMN `count` DECIMAL(15,3) NOT NULL DEFAULT '0'";
$model->exec($sql);

$sql = "ALTER TABLE `shop_cart_items` MODIFY COLUMN `quantity` DECIMAL(15,3) NOT NULL DEFAULT '1'";
$model->exec($sql);
