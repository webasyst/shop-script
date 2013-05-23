<?php

$model = new waModel();

// if at least on stock for sku isn't exist - make sku count NULL
$stock_model = new shopStockModel();
$n = $stock_model->countAll();
$sql = "
    UPDATE shop_product_skus s JOIN (
        SELECT sk.id
        FROM shop_product_skus sk
        JOIN shop_product_stocks st ON sk.id = st.sku_id
        GROUP BY sk.id
        HAVING COUNT( * ) < $n) AS t ON s.id = t.id
    SET s.count = NULL
";
$model->exec($sql);

// if sku count IS NULL product count IS NULL but ...
$sql = "
    UPDATE shop_product p
    JOIN shop_product_skus s ON s.product_id = p.id
    SET p.count = NULL
    WHERE s.count IS NULL AND s.available != 1
";
$model->exec($sql);

// ...if all sku are unavailble for this product count of product must be 0
$sql = "
UPDATE shop_product p JOIN (
    SELECT p.id, p.count, SUM(sk.available) all_sku_available
    FROM shop_product p
    JOIN shop_product_skus sk ON p.id = sk.product_id
    WHERE p.count IS NULL || (p.count IS NOT NULL AND p.count != 0)
    GROUP BY p.id
    HAVING all_sku_available = 0
) r ON p.id = r.id
SET p.count = 0
";

$model->exec($sql);