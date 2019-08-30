<?php

// Move the OneSignal settings from the Shop-Script to the Framework
try {

    // Default push
    $push = wa()->getPush();
    if ($push->isEnabled()) {
        return;
    }

    // OneSignal push
    $push_onesignal = wa()->getPush('onesignal');
    $onesignal_system_key = $push_onesignal->getSettings('api_token');
    if (!empty($onesignal_system_key)) {
        return;
    }

    $app_settings_model = new waAppSettingsModel();
    $web_push_domains = json_decode($app_settings_model->get('shop', 'web_push_domains', '{}'), true);
    if (!is_array($web_push_domains)) {
        $web_push_domains = [];
    }

    if (empty($web_push_domains)) {
        return;
    }

    $onesignal_shop_key = null;

    foreach ($web_push_domains as $domain => $data) {
        $is_active = (ifempty($data, 'active', 0) == 1);
        if (!$is_active) {
            continue;
        }

        if (!empty($data['rest_api_key'])) {
            $onesignal_shop_key = $data['rest_api_key'];
            break;
        }
    }

    if (empty($onesignal_shop_key)) {
        return;
    }

    $push_onesignal->saveSettings(array('api_token' => $onesignal_shop_key));
    $app_settings_model->set('webasyst', 'push_adapter', 'onesignal');
    $app_settings_model->del('shop', 'web_push_domains');

} catch (Exception $e) {

}