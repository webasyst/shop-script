<?php

$modified_config_path = wa()->getConfig()->getConfigPath('workflow.php', true, 'shop');
if (file_exists($modified_config_path)) {
    $original_config_path = wa()->getAppPath('lib/config/data/workflow.php', 'shop');
    if (file_exists($original_config_path)) {
        $orig_cfg = include($original_config_path);
        $cfg = shopWorkflow::getConfig();
        $something_changed = false;

        // Add new states
        foreach (['fbs', 'fbm'] as $state_id) {
            if (empty($cfg['states'][$state_id]) && !empty($orig_cfg['states'][$state_id])) {
                $cfg['states'][$state_id] = $orig_cfg['states'][$state_id];

                // Make sure its name is translated. This is a workaround for a fact that gettext localization
                // does not become available right away after an app update.
                if (wa()->getLocale() != 'en_US' && get_class(waLocale::$adapter) != 'waLocalePHPAdapter') {
                    $state = $cfg['states'][$state_id];
                    $loc = new waLocalePHPAdapter();
                    $locale_path = wa()->getAppPath('locale', 'shop');
                    $loc->load(wa()->getLocale(), $locale_path, 'shop', false);
                    $state['name'] = $loc->dgettext('shop', $state['name']);
                    $cfg['states'][$state_id] = $state;
                }

                $something_changed = true;
            }
        }

        if ($something_changed) {
            // Save changes
            shopWorkflow::setConfig($cfg);
        }
    }
}