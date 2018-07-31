<?php

$model = new waModel();

try {
    $model->exec("ALTER TABLE shop_page MODIFY content mediumtext NOT NULL");
    $model->exec("ALTER TABLE shop_product_pages MODIFY content mediumtext NOT NULL");
    $model->exec("ALTER TABLE shop_category MODIFY description mediumtext");
    $model->exec("ALTER TABLE shop_product MODIFY description mediumtext");
} catch (waException $e) {

}