<?php

$model = new waModel();
$plugin_model = new shopPluginModel();
$plugings = $plugin_model->getByField('plugin', 'invoicejur', true);

if ($plugings) {
    foreach ($plugings as $p) {
        $settings = $model->query("SELECT name, value FROM shop_plugin_settings WHERE id = i:0", $p['id'])->fetchAll('name', true);
        $company = !empty($settings['cust_company']) ? $settings['cust_company'] : 'company';
        $inn = !empty($settings['cust_inn']) ? $settings['cust_inn'] : 'inn';

        $model->exec("UPDATE shop_order_params SET name = 'payment_params_".$company."' WHERE name = 'billing_".$company."'");
        $model->exec("UPDATE shop_order_params SET name = 'payment_params_".$inn."' WHERE name = 'billing_".$inn."'");
    }
}

