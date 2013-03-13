<?php

$model = new waModel();

try {
    $model->exec("SELECT auth_provider FROM `shop_product_reviews` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_product_reviews` ADD auth_provider VARCHAR (100) NULL DEFAULT NULL");
}

try {
    $model->exec("SELECT ip FROM `shop_product_reviews` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_product_reviews` ADD ip INT (11) NULL DEFAULT NULL");
}