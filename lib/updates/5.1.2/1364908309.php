<?php

$model = new waModel();
try {
    $model->query("SELECT virtual FROM shop_product_skus WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_product_skus` ADD `virtual` TINYINT( 1 ) NOT NULL DEFAULT '0'");
}