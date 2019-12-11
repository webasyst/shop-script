<?php

$_file_path = wa()->getAppPath('lib/handlers/webasyst.settings_sidebar.handler.php', 'shop');

if (file_exists($_file_path)) {
    try {
        waFiles::delete($_file_path);
    } catch (Exception $e) {
    }
}

waAppConfig::clearAutoloadCache('shop');