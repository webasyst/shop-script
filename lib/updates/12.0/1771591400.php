<?php

$modified_config_path = wa()->getConfig()->getConfigPath('workflow.php', true, 'shop');
if (file_exists($modified_config_path)) {
    $original_config_path = wa()->getAppPath('lib/config/data/workflow.php', 'shop');
    if (file_exists($original_config_path)) {
        $orig_cfg = include($original_config_path);
        $cfg = shopWorkflow::getConfig();
        $something_changed = false;

        // Add a new state
        if (empty($cfg['states']['fulfilling']) && !empty($orig_cfg['states']['fulfilling'])) {
            $cfg['states']['fulfilling'] = $orig_cfg['states']['fulfilling'];
            $something_changed = true;
        }

        // Add a new action
        if (empty($cfg['actions']['fulfilling']) && !empty($orig_cfg['actions']['fulfilling'])) {
            $cfg['actions']['fulfilling'] = $orig_cfg['actions']['fulfilling'];

            // Make sure its name is translated. This is a workaround for a fact that gettext localization
            // does not become available right away after an app update.
            if (wa()->getLocale() != 'en_US' && get_class(waLocale::$adapter) != 'waLocalePHPAdapter') {
                $a = $cfg['actions']['fulfilling'];
                $loc = new waLocalePHPAdapter();
                $locale_path = wa()->getAppPath('locale', 'shop');
                $loc->load(wa()->getLocale(), $locale_path, 'shop', false);
                $a['name'] = $loc->dgettext('shop', $a['name']);
                $a['options']['log_record'] = $loc->dgettext('shop', $a['options']['log_record']);
                $cfg['actions']['fulfilling'] = $a;
                if ($something_changed) {
                    $cfg['states']['fulfilling']['name'] = $loc->dgettext('shop', $cfg['states']['fulfilling']['name']);
                }
            }

            // Make it available in certain states
            //foreach(['new', 'processing', 'paid'] as $state_id) {
            //    if (isset($cfg['states'][$state_id]['available_actions']) && !in_array('fulfilling', $cfg['states'][$state_id]['available_actions'])) {
            //        $cfg['states'][$state_id]['available_actions'][] = 'fulfilling';
            //    }
            //}

            $something_changed = true;
        }


        if ($something_changed) {
            // Save changes
            shopWorkflow::setConfig($cfg);
        }
    }
}
