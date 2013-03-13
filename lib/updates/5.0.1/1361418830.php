<?php

$model = new waModel();

try {
    $sql = "DELETE cp.* FROM `shop_category_products` cp
            LEFT JOIN `shop_category` c ON c.id = cp.category_id
            WHERE c.id IS NULL";
    $model->query($sql);

    $product_model = new shopProductModel();
    $product_model->correctMainCategory();
} catch (Exception $e) {
    if (class_exists('waLog')) {
        waLog::log(basename(__FILE__).': '.$e->getMessage(), 'shop-update.log');
    }
    throw $e;
}