<?php

$model = new waModel();

$sql = "
    UPDATE `shop_product` p
    JOIN `shop_product_skus` s ON s.product_id = p.id
    SET p.count = NULL
    WHERE s.count IS NULL AND p.count IS NOT NULL
";

$model->exec($sql);