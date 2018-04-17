<?php

try {
    $path = wa('shop', 1)->getConfig()->getConfigPath('config.php');

    if (file_exists($path)) {
        $config = include($path);
        if (isset($config['factories']['captcha'])) {

            //save old settings
            waLog::log("Old captcha settings : \n".var_export($config['factories']['captcha'], true), 'shop/shop-update.log');

            unset($config['factories']['captcha']);
            waUtils::varExportToFile($config, $path);
        }
    }
} catch (Exception $e) {

}