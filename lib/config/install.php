<?php

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

// currency
$currency_model = new shopCurrencyModel();
$model = new waAppSettingsModel();
$model->set($app_id, 'welcome', 1);
if ($currency_model->countAll() == 0) {


    $locale = waLocale::getInfo(wa()->getUser()->getLocale());
    $country_iso3 = isset($locale['iso3']) ? $locale['iso3'] : 'usa';

    if (isset($this) && ($this instanceof waAppConfig)) {
        $config = $this;
    } else {
        $config = wa()->getConfig();
    }
    $path = $config->getConfigPath('data/welcome/', false, $app_id);
    $path .= "country_{$country_iso3}.php";

    if (file_exists($path)) {
        $country_data = include($path);

        # Main country setting
        $model = new waAppSettingsModel();
        $model->set($app_id, 'country', $country_iso3);
    } else {
        $country_data = array();
    }

    if (empty($country_data) || empty($country_data['currency'])) {
        $country_data = array(
            'currency' => array(
                'USD' => 1.0,
            ),
        );
    }

    #currency
    $sort = 0;
    foreach ($country_data['currency'] as $code => $rate) {

        // Ignore if currency already exists
        if (!$currency_model->getById($code)) {
            $currency_model->insert(compact('code', 'rate', 'sort'), 2);
        }

        if ($sort == 0) {
            $currency_model->deleteCache();
            $model->set($app_id, 'currency', $code);
        }
        ++$sort;
    }
    $currency_model->deleteCache();

    $model->set($app_id, 'use_product_currency', 'true');
}

// notifications
$notifications_model = new shopNotificationModel();
if ($notifications_model->countAll() == 0) {
    $notifications_action = new shopSettingsNotificationsAddAction();
    $notifications = $notifications_action->getTemplates();
    $params_model = new shopNotificationParamsModel();
    $events = $notifications_action->getEvents();
    foreach ($notifications as $event => $n) {
        if ($event == 'order') {
            continue;
        }
        $data = array(
            'name'      => $events[$event]['name'].' ('._w('Customer').')',
            'event'     => $event,
            'transport' => 'email',
            'status'    => 1,
        );
        $id = $notifications_model->insert($data);
        $params = $n;
        $params['to'] = 'customer';
        $params_model->save($id, $params);

        if ($event == 'order.create') {
            $data['name'] = $events[$event]['name'].' ('._w('Store admin').')';
            $id = $notifications_model->insert($data);
            $params['to'] = 'admin';
            $params_model->save($id, $params);
        }
    }
}

// Unless we're called from another application, redirect to backend welcome screen
if (wa()->getEnv() == 'backend' && !wa()->getApp()) {
    // redirect to welcome
    header("Location: ".wa()->getConfig()->getBackendUrl(true).'shop/?action=welcome');
}
