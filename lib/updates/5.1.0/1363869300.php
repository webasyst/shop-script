<?php

$model = new waModel();

$sql = "
    UPDATE shop_product p JOIN (
        SELECT p.id, MAX(ps.price)*c.rate AS max_price, MIN(ps.price)*c.rate AS min_price
        FROM `shop_product` p
            JOIN `shop_product_skus` ps ON p.id = ps.product_id
            JOIN `shop_currency` c ON c.code = p.currency
        GROUP BY ps.product_id
    ) r ON p.id = r.id
    SET p.max_price = r.max_price, p.min_price = r.min_price
";

$model->exec($sql);