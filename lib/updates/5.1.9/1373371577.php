<?php

$model = new waModel();
$model->exec("DELETE pf FROM shop_product_features pf LEFT JOIN shop_product_skus s ON pf.sku_id = s.id
WHERE pf.sku_id IS NOT NULL AND s.id IS NULL ");

$model->exec("DELETE FROM shop_feature WHERE (type = '1' OR type = '') AND (name = '' OR name IS NULL)");
$model->exec("UPDATE shop_feature SET type = 'varchar' WHERE type = '1' OR type = ''");