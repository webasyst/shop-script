<?php

$model = new waModel();

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

$sql = "
    UPDATE shop_product p
    JOIN shop_product_skus s ON s.product_id = p.id
    SET p.count = NULL
    WHERE s.count IS NULL
";

$model->exec($sql);

