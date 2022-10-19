<?php

$model = new waModel();
$installer = new shopInstaller();

try {
    $model->exec("SELECT 1 FROM `shop_filter` WHERE 0");
} catch(Exception $e) {
    $installer->createTable('shop_filter');
}

try {
    $model->exec("SELECT 1 FROM `shop_filter_rules` WHERE 0");
} catch(Exception $e) {
    $installer->createTable('shop_filter_rules');
}

