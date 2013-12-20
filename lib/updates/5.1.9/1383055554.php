<?php

$stock_model = new shopStockModel();
$n = $stock_model->countAll();
if ($n > 0) {
    // repair invariant: all N stocks exists, but sku_cout IS NULL
    $sql = "
    UPDATE shop_product_skus s JOIN (        
        SELECT sk.id, SUM(st.count) cnt
        FROM shop_product_skus sk
        JOIN shop_product_stocks st ON sk.id = st.sku_id
        WHERE sk.count IS NULL
        GROUP BY sk.id
        HAVING COUNT( * ) = $n
    ) AS t ON s.id = t.id
    SET s.count = t.cnt";
    $stock_model->exec($sql);
    
    $product_model = new shopProductModel();    
    $product_model->correctCount();
    
}
