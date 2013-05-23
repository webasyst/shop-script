<?php

$model = new waModel();

$sql = "
    UPDATE shop_product p JOIN (
        SELECT p.id, SUM(sk.available) all_sku_available
        FROM shop_product p
        JOIN shop_product_skus sk ON p.id = sk.product_id
        WHERE p.count IS NULL || (p.count IS NOT NULL AND p.count != 0)
        GROUP BY p.id
        HAVING all_sku_available = 0
    ) t ON p.id = t.id
    SET p.count = 0
";
$model->exec($sql);