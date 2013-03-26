<?php

$model = new waModel();

// SKUS: clear image_id if image doesn't exist
$model->exec("
    UPDATE `shop_product_skus` ps
    LEFT JOIN `shop_product_images` pi ON pi.id = ps.image_id AND pi.product_id = ps.product_id
    SET ps.image_id = NULL
    WHERE ps.image_id IS NOT NULL AND pi.id IS NULL
");

// PRODUCTS: repair main image link if broken
$model->exec("
    UPDATE `shop_product` p
    JOIN `shop_product_images` pi ON pi.product_id = p.id
    JOIN (
            SELECT pi.product_id, MIN(pi.sort) min_sort
            FROM `shop_product_images` pi
            GROUP BY pi.product_id
    ) t ON pi.product_id = t.product_id AND pi.sort = t.min_sort
    SET p.image_id = pi.id, p.ext = pi.ext
    WHERE p.image_id != pi.id OR p.ext != pi.ext OR p.image_id IS NULL
");

// PRODUCTS: if there isn't any image for product ID clear product.image_id AND product.ext
$model->exec("
        UPDATE `shop_product` p
        LEFT JOIN `shop_product_images` pi ON pi.product_id = p.id
        SET p.image_id = NULL, p.ext = NULL
        WHERE pi.id IS NULL
");