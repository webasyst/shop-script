<?php
$target_path = wa()->getDataPath('products/', true, 'shop');
$source_path = wa()->getAppPath('lib/config/data/', 'shop');

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

$target_path = wa()->getDataPath('promos/', true, 'shop');

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
$model->set('shop', 'welcome', 1);
if ($currency_model->countAll() == 0) {
    $currency_model->insert(array(
        'code' => 'USD',
        'rate' => 1.000,
        'sort' => 1,
    ), 2);
    $currency_model->deleteCache();

    $model->set('shop', 'currency', 'USD');
    $model->set('shop', 'use_product_currency', 'true');
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
