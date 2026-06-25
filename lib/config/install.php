<?php

//
// This script is executed once, the first itme Shop app is initialized after installation.
// (Still, it has to allow multiple executions without bad consequences, because in exceptional cases
// like fatal errors it can be run several times.)
//
// This script also runs when user performs full app reset and all data from all app tables is deleted.
//
// Second part of install script is inside install.after.php
// Second part is only allowed to run after a logged-in admin user opens the app for the first time.
//

/** @var shopConfig $this */
/** @var string $app_id */
if (empty($app_id)) {
    $app_id = 'shop';
}

// generate image thumbs on demand
(new shopInstaller())->ensureThumbPhp();

$model = new waAppSettingsModel();
$model->set($app_id, 'create_locale_configs', 1);
$model->set($app_id, 'welcome', 1);

// Initializing currencies and notifications is moved to a separate file,
// see install.after.php

//shipping plugins
try {
    $plugins = shopShipping::getList();
    $default_enabled_plugins = array('dummy');
    foreach ($plugins as $plugin => $info) {
        if (in_array($plugin, $default_enabled_plugins, true)) {
            $info['status'] = 1;
        }
        $info['plugin'] = $plugin;
        shopShipping::savePlugin($info);
    }
} catch (waException $ex) {
    waLog::log($ex->getMessage(), 'wa-apps/shop/install.log');
}

// Unless we're called from another application, redirect to backend welcome screen
if (wa()->getEnv() == 'backend' && !wa()->getApp()) {
    // redirect to welcome
    header("Location: ".wa()->getConfig()->getBackendUrl(true).'shop/?action=welcome');
}
