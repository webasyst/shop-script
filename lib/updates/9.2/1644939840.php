<?php

$model = new waModel();
$installer = new shopInstaller();

try {
    $model->exec("SELECT 1 FROM `shop_presentation` WHERE 0");
} catch(Exception $e) {
    $installer->createTable('shop_presentation');
}

try {
    $model->exec("SELECT 1 FROM `shop_presentation_columns` WHERE 0");
} catch(Exception $e) {
    $installer->createTable('shop_presentation_columns');
}
