<?php

$_model = new waModel();

$_columns = $_model->query("SHOW COLUMNS FROM `shop_plugin_settings`")
    ->fetchAll('Field');

$_is_value_is_text_type = isset($_columns['value']) && isset($_columns['value']['Type']) && ( $_columns['value']['Type'] === 'text' || $_columns['value']['Type'] === 'TEXT' );
if ($_is_value_is_text_type) {
    try {
        $_model->exec("ALTER TABLE `shop_plugin_settings` CHANGE `value` `value` MEDIUMTEXT NOT NULL");
    } catch (waDbException $e) {

    }
}
