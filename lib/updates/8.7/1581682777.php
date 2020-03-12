<?php
// New workflow action: edit product codes
$cfg = shopWorkflow::getConfig();
if (empty($cfg['actions']['editcode'])) {
    $file = wa()->getAppPath('lib/config/data/workflow.php', 'shop');
    if (file_exists($file)) {
        $orig_cfg = include($file);
        if (!empty($orig_cfg['actions']['editcode'])) {
            // Add the action
            $cfg['actions']['editcode'] = $orig_cfg['actions']['editcode'];

            // Make sure its name is translated. This is a workaround for a fact that gettext localization
            // does not become available right away after an app update.
            if (wa()->getLocale() != 'en_US' && get_class(waLocale::$adapter) != 'waLocalePHPAdapter') {
                $a = $cfg['actions']['editcode'];
                $loc = new waLocalePHPAdapter();
                $locale_path = wa()->getAppPath('locale', 'shop');
                $loc->load(wa()->getLocale(), $locale_path, 'shop', false);
                $a['name'] = $loc->dgettext('shop', $a['name']);
                $a['options']['log_record'] = $loc->dgettext('shop', $a['options']['log_record']);
                $cfg['actions']['editcode'] = $a;
            }

            // Make it available in certain states
            foreach(array('new', 'processing', 'paid', 'shipped', 'completed') as $state_id) {
                if (isset($cfg['states'][$state_id]['available_actions']) && !in_array('editcode', $cfg['states'][$state_id]['available_actions'])) {
                    $cfg['states'][$state_id]['available_actions'][] = 'editcode';
                }
            }

            // Save changes
            shopWorkflow::setConfig($cfg);
        }
    }
}
