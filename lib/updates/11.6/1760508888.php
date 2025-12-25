<?php
$model = new waModel();
$installer = new shopInstaller();
foreach (['shop_sales_channel', 'shop_sales_channel_params'] as $t) {
    try {
        $model->exec("SELECT 1 FROM `{$t}` WHERE 0");
    } catch(Exception $e) {
        $installer->createTable($t);
    }
}
