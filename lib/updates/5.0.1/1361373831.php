<?php

$model = new waModel();
try {
    $model->exec("SELECT category_id FROM shop_product WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE shop_product ADD category_id INT (11) NULL DEFAULT NULL");
}

$product_model = new shopProductModel();
$product_model->correctMainCategory();