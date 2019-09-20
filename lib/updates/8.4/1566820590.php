<?php
$model = new shopPluginModel();
$options = array(
    'all'  => true,
    'info' => true,
);
$plugins = $model->listPlugins(shopPluginModel::TYPE_PAYMENT, $options);

foreach ($plugins as $id => $plugin) {
    $model->updateById($id, $plugin);
}
