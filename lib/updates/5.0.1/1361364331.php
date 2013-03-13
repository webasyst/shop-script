<?php

$model = new waModel();
try {
    $format = $model->query("SELECT value FROM `wa_app_settings` WHERE app_id = 'shop' AND name = 'order_format'")->fetchField('value');
    if ($format) {
        $format = str_replace('%id%', '{$order.id}', $format);
        $format = $model->exec("UPDATE `wa_app_settings` SET value = '".$format."' WHERE app_id = 'shop' AND name = 'order_format'");
    }
} catch (Exception $e) {
    if (class_exists('waLog')) {
        waLog::log(basename(__FILE__).': '.$e->getMessage(), 'shop-update.log');
    }
}