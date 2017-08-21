<?php
$app_config = wa('shop');
$custom_path = $app_config->getConfigPath('shop/plugins/cml1c').'/config.php';
if (file_exists($custom_path)) {
    $custom_config = @include($custom_path);
    if (is_array($custom_config)) {
        if (isset($custom_config['time_gap'])) {
            $model = new waAppSettingsModel();
            $model->set('shop.cml1c', 'time_gap', max(0, intval($custom_config['time_gap'])));
            unset($custom_config['time_gap']);
        }

        if (count($custom_config)) {
            waFiles::move($custom_path, preg_replace('@\.php$@', '.bak.php', $custom_path));
            waUtils::varExportToFile($custom_path, $custom_path);
        } else {
            waFiles::delete($custom_path);
        }
    }
}
