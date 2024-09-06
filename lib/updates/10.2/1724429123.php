<?php
// New workflow action: edit product codes
$modified_config_path = wa()->getConfig()->getConfigPath('workflow.php', true, 'shop');
if (file_exists($modified_config_path)) {
    $original_config_path = wa()->getAppPath('lib/config/data/workflow.php', 'shop');
    if (file_exists($original_config_path)) {
        $orig_cfg = include($original_config_path);
        $cfg = shopWorkflow::getConfig();
        $something_changed = false;

        // Add a new state
        if (empty($cfg['states']['pos']) && !empty($orig_cfg['states']['pos'])) {
            $cfg['states']['pos'] = $orig_cfg['states']['pos'];
            $something_changed = true;
        }

        // Add a new action
        if (empty($cfg['actions']['initpay']) && !empty($orig_cfg['actions']['initpay'])) {
            $cfg['actions']['initpay'] = $orig_cfg['actions']['initpay'];

            // Make sure its name is translated. This is a workaround for a fact that gettext localization
            // does not become available right away after an app update.
            if (wa()->getLocale() != 'en_US' && get_class(waLocale::$adapter) != 'waLocalePHPAdapter') {
                $a = $cfg['actions']['initpay'];
                $loc = new waLocalePHPAdapter();
                $locale_path = wa()->getAppPath('locale', 'shop');
                $loc->load(wa()->getLocale(), $locale_path, 'shop', false);
                $a['name'] = $loc->dgettext('shop', $a['name']);
                $a['options']['log_record'] = $loc->dgettext('shop', $a['options']['log_record']);
                $cfg['actions']['initpay'] = $a;
            }

            // Make it available in certain states
            foreach(array('new', 'processing', 'pos') as $state_id) {
                if (isset($cfg['states'][$state_id]['available_actions']) && !in_array('initpay', $cfg['states'][$state_id]['available_actions'])) {
                    $cfg['states'][$state_id]['available_actions'][] = 'initpay';
                }
            }

            $something_changed = true;
        }

        if ($something_changed) {
            // Save changes
            shopWorkflow::setConfig($cfg);
        }
    }
}
