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

$target_path = wa()->getDataPath('products/', true, $app_id);
$source_path = wa()->getAppPath('lib/config/data/', $app_id);

// generate product thumb via php on demand
$target = $target_path.'thumb.php';
if (!file_exists($target)) {
    $php_file = '<?php
$file = dirname(__FILE__)."/../../../../"."/wa-apps/shop/lib/config/data/thumb.php";

if (file_exists($file)) {
    include($file);
} else {
    header("HTTP/1.0 404 Not Found");
}
';
    waFiles::write($target, $php_file);
}

$target = $target_path.'.htaccess';
if (!file_exists($target)) {
    waFiles::copy($source_path.'.htaccess', $target);
}

// generate promos thumb via php on demand
$target_path = wa()->getDataPath('promos/', true, $app_id);

$target = $target_path.'thumb.php';
if (!file_exists($target)) {
    $file = '<?php
$file = dirname(__FILE__)."/../../../../"."wa-apps/shop/lib/config/data/promos.thumb.php";

if (file_exists($file)) {
    include($file);
} else {
    header("HTTP/1.0 404 Not Found");
}
';
    waFiles::write($target, $file);
}

$target = $target_path.'.htaccess';
if (!file_exists($target)) {
    waFiles::copy($source_path.'.htaccess', $target);
}

$model = new waAppSettingsModel();
$model->set($app_id, 'create_locale_configs', 1);
$model->set($app_id, 'welcome', 1);

// Initializing currencies and notifications is moved to a separate file,
// see install.after.php

//payment plugins
try {
    $plugins = shopPayment::getList();
    unset($plugins['dummy']); // do not set up "Manual payment"
    foreach ($plugins as $plugin => $info) {
        if (!empty($info['pos_initiates_payment'])) {
            continue;
        }
        $info['plugin'] = $plugin;
        shopPayment::savePlugin($info);
    }
} catch (waException $ex) {
    waLog::log($ex->getMessage(), 'wa-apps/shop/install.log');
}

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
