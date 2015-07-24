<?php

$model = new waModel();

// add shop_product_images.filename
try {
    $model->query('SELECT filename FROM shop_product_images WHERE 0');
} catch (waDbException $e) {
    $model->query("ALTER TABLE shop_product_images ADD filename VARCHAR(255) NOT NULL DEFAULT '' AFTER size");
}

// add shop_product.image_filename
try {
    $model->query('SELECT image_filename FROM shop_product WHERE 0');
} catch (waDbException $e) {
    $model->query("ALTER TABLE shop_product ADD image_filename VARCHAR(255) NOT NULL DEFAULT '' AFTER image_id");
}