<?php

try {
    $modified_config_path = wa()->getConfig()->getConfigPath('workflow.php', true, 'shop');
    if (file_exists($modified_config_path)) {
        $original_config_path = wa()->getAppPath('lib/config/data/workflow.php', 'shop');
        if (file_exists($original_config_path)) {
            $orig_cfg = include($original_config_path);
            $cfg = shopWorkflow::getConfig();

            // Add a new action
            if (empty($cfg['actions']['sendpin']) && !empty($orig_cfg['actions']['sendpin'])) {
                $cfg['actions']['sendpin'] = $orig_cfg['actions']['sendpin'];

                // Make sure its name is translated. This is a workaround for a fact that gettext localization
                // does not become available right away after an app update.
                if (wa()->getLocale() != 'en_US' && get_class(waLocale::$adapter) != 'waLocalePHPAdapter') {
                    $a = $cfg['actions']['sendpin'];
                    $loc = new waLocalePHPAdapter();
                    $locale_path = wa()->getAppPath('locale', 'shop');
                    $loc->load(wa()->getLocale(), $locale_path, 'shop', false);
                    $a['name'] = $loc->dgettext('shop', $a['name']);
                    $a['options']['log_record'] = $loc->dgettext('shop', $a['options']['log_record']);
                    $cfg['actions']['sendpin'] = $a;
                    $cfg['states']['sendpin']['name'] = $loc->dgettext('shop', $cfg['states']['sendpin']['name']);
                }

                // Make it available in certain states
                foreach(['new', 'processing', 'auth', 'paid', 'shipped', 'pos', 'pickup'] as $state_id) {
                    if (isset($cfg['states'][$state_id]['available_actions']) && !in_array('sendpin', $cfg['states'][$state_id]['available_actions'])) {
                        $cfg['states'][$state_id]['available_actions'][] = 'sendpin';
                    }
                }

                // Save changes
                shopWorkflow::setConfig($cfg);
            }
        }
    }
} catch (Exception $e) {
    if (class_exists('waLog')) {
        waLog::log(basename(__FILE__).': '.$e->getMessage(), 'shop-update.log');
    }
    exit();
}


$notifications_model = new shopNotificationModel();
$params_model = new shopNotificationParamsModel();
$notifications_action = new shopSettingsNotificationsAddAction();
$notifications = $notifications_action->getTemplates();
$params_model = new shopNotificationParamsModel();
$events = $notifications_action->getEvents();

foreach ($notifications as $event => $n) {
    if ($event != 'order.sendpin') {
        continue;
    }
    $data = [
        'name'      => ifset($events, $event, 'name', $event).' ('._w('Customer').')',
        'event'     => $event,
        'transport' => 'email',
        'status'    => 1,
    ];
    $id = $notifications_model->insert($data);
    $params = ['to' => 'customer'] + array_diff_key($n, ['sms' => 1]);
    $params_model->save($id, $params);

    $data = [
        'transport' => 'sms',
        'status' => 0
    ] + $data;
    $id = $notifications_model->insert($data);
    $params = [
       'to' => 'customer',
       'text' => $n['sms'],
    ] + array_diff_key($n, ['subject' => 1, 'sms' => 1, 'body' => 1]);
    $params_model->save($id, $params);
}
