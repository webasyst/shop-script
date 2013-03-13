<?php
$model = new waModel();
try {
    $model->query("UPDATE `shop_plugin` SET `plugin` = LOWER(`plugin`) WHERE `type` IN ('shipping','payment')");
} catch (Exception $e) {
    if (class_exists('waLog')) {
        waLog::log(basename(__FILE__).': '.$e->getMessage(), 'shop-update.log');
    }
    throw $e;
}
