<?php

$model = new waModel();
try {
    $model->exec("SELECT keywords FROM `shop_product_pages` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_product_pages` ADD keywords TEXT NULL");
}

try {
    $model->exec("SELECT description FROM `shop_product_pages` WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `shop_product_pages` ADD description TEXT NULL");
}

try {
    $model->exec("SELECT * FROM `shop_product_page_params` WHERE 0");

    $data = array();
    foreach ($model->query("SELECT * FROM `shop_product_page_params` WHERE name IN ('keywords', 'description')") as $item)
    {
        $data[$item['page_id']][$item['name']] = $item['value'];
    }

    $page_model = new shopProductPagesModel();
    foreach ($data as $page_id => $item) {
        $page_model->updateById($page_id, $item);
    }
    $model->exec("DROP TABLE `shop_product_page_params`");
} catch (waDbException $e) {
}
