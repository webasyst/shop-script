<?php

$model = new waModel();
try {
    $model->exec("ALTER TABLE  `shop_product` ADD INDEX  `total_sales` (  `total_sales` )");
} catch (waDbException $e) {

}


// remove old table and model
try {
    $model->exec("SELECT * FROM `shop_product_page_params` WHERE 0");
    $model->exec("DROP TABLE `shop_product_page_params`");
} catch (waDbException $e) {
}
waFiles::delete($this->getAppPath('lib/models/shopProductPageParams.model.php'), true);
