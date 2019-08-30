<?php

$_file_paths = [
    wa()->getAppPath('lib/actions/orders/shopOrdersWebPushSubscribe.controller.php', 'shop')
];
foreach ($_file_paths as $_file_path) {
    if (file_exists($_file_path)) {
        try {
            waFiles::delete($_file_path);
        } catch (Exception $e) {
        }
    }
}

waAppConfig::clearAutoloadCache('shop');