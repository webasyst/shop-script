<?php

$model = new waModel();

try {
    $model->query("SELECT sku_count FROM shop_product WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE shop_product ADD sku_count INT (11) NOT NULL DEFAULT 1");
}

$model->exec("UPDATE shop_product p JOIN (
    SELECT product_id, COUNT( * ) sku_count
    FROM shop_product_skus
    GROUP BY product_id HAVING count(*) > 1
) t ON p.id = t.product_id SET p.sku_count = t.sku_count");