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
    $to_admin_sms = 'admin';
    $admin_phone = wa()->getUser()->get('phone', 'default');
    if ($admin_phone) {
        $to_admin_sms = $admin_phone;
    }
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
        $params = ['to' => 'customer'] + array_diff_key($n, ['sms' => 1]);
        $params_model->save($id, $params);

        if ($event == 'order.process' || $event == 'order.ship') {

            $data = [
                'transport' => 'sms',
            ] + $data;
            $id = $notifications_model->insert($data);
            $params = [
                'to' => 'customer',
                'text' => $n['sms'],
            ] + array_diff_key($n, ['subject' => 1, 'sms' => 1, 'body' => 1]);
            $params_model->save($id, $params);

        } else if ($event == 'order.create') {

            $data = [
                'name' => $events[$event]['name'] . ' (' . _w('Store admin') . ')',
                'transport' => 'email',
            ] + $data;
            $id = $notifications_model->insert($data);
            $params = [
                'to' => 'admin',
            ] + $params;
            $params_model->save($id, $params);

            $data = [
                'name' => ifset($events, $event, 'name', $event) . ' (' . _w('Customer') . ')',
                'transport' => 'sms',
            ] + $data;
            $id = $notifications_model->insert($data);
            $params = [
                'to' => 'customer',
                'text' => $n['sms'],
            ] + array_diff_key($n, ['subject' => 1, 'sms' => 1, 'body' => 1]);
            $params_model->save($id, $params);

            $data = [
                'name' => $events[$event]['name'] . ' (' . _w('Store admin') . ')',
                'transport' => 'sms',
            ] + $data;
            $id = $notifications_model->insert($data);
            $params = [
                'to' => $to_admin_sms,
                'text' => $n['sms'],
            ] + array_diff_key($n, ['subject' => 1, 'sms' => 1, 'body' => 1]);
            $params_model->save($id, $params);

        }
    }
}

/** Product quantity units */
$locale      = wa()->getUser()->getLocale();
$locale_file = wa()->getAppPath("lib/config/data/units.$locale.php", 'shop');
$unit_model = new shopUnitModel();

if (file_exists($locale_file) && $unit_model->countAll() == 0) {
    $insert = [];
    $units  = include $locale_file;
    $sql    = "INSERT IGNORE INTO shop_unit
        (okei_code, name, short_name, storefront_name, status)
    VALUES ";

    foreach ($units as $unit) {
        $insert[] = "('".implode("', '", $unit)."', 0)";
    }

    if (!empty($insert)) {
        $sql .= implode(', ', $insert);
        $unit_model->exec($sql);
    }
}

