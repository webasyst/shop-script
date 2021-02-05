<?php

//
// This is the second part of app initialization script. It runs the first time
// when a logged-in admin user opens the app.
// See shopConfig->installAfter(), install.php
//

$app_id = 'shop';
$model = new waAppSettingsModel();

// currency
$currency_model = new shopCurrencyModel();
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
        if (empty($currency_model->getById($code))) {
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
            'name' => ifset($events, $event, 'name', $event) . ' (' . _w('Customer') . ')',
            'event' => $event,
            'transport' => 'email',
            'status' => 1,
        );
        $id = $notifications_model->insert($data);
        $params = $n;
        $params['to'] = 'customer';
        $params_model->save($id, $params);

        if ($event == 'order.create') {
            $data['name'] = $events[$event]['name'] . ' (' . _w('Store admin') . ')';
            $id = $notifications_model->insert($data);
            $params['to'] = 'admin';
            $params_model->save($id, $params);
        }
    }
}
