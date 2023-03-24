<?php

$model = new waModel();

try {
    $model->query("SELECT `sort` FROM `shop_product_related` WHERE 0");
} catch(Exception $e) {
    $model->exec("ALTER TABLE `shop_product_related` ADD COLUMN `sort` INT(11) NOT NULL DEFAULT 0");
}
