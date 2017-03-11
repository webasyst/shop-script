<?php
// Shipping date column
$m = new waModel();
try {
    $m->query("SELECT `shipping_datetime` FROM `shop_order` WHERE 0");
} catch (waDbException $e) {
    $m->exec("ALTER TABLE `shop_order`
        ADD `shipping_datetime` DATETIME NULL DEFAULT NULL,
        ADD INDEX `shipping_datetime` (`shipping_datetime`)");
}

// New workflow action: edit shipping details
$cfg = shopWorkflow::getConfig();
if (empty($cfg['actions']['editshippingdetails'])) {
    $file = wa()->getAppPath('lib/config/data/workflow.php', 'shop');
    if (file_exists($file)) {
        $orig_cfg = include($file);
        if (!empty($orig_cfg['actions']['editshippingdetails'])) {
            // Add the action
            $cfg['actions']['editshippingdetails'] = $orig_cfg['actions']['editshippingdetails'];

            // Make sure its name is translated. This is a workaround for a fact that gettext localization
            // does not become available right away after an app update.
            if (wa()->getLocale() != 'en_US' && get_class(waLocale::$adapter) != 'waLocalePHPAdapter') {
                $a = $cfg['actions']['editshippingdetails'];
                $loc = new waLocalePHPAdapter();
                $locale_path = wa()->getAppPath('locale', 'shop');
                $loc->load(wa()->getLocale(), $locale_path, 'shop', false);
                $a['name'] = $loc->dgettext('shop', $a['name']);
                $a['options']['log_record'] = $loc->dgettext('shop', $a['options']['log_record']);
                $cfg['actions']['editshippingdetails'] = $a;
            }

            // Make it available in certain states
            foreach(array('new', 'processing', 'paid', 'shipped') as $state_id) {
                if (isset($cfg['states'][$state_id]['available_actions']) && !in_array('editshippingdetails', $cfg['states'][$state_id]['available_actions'])) {
                    $cfg['states'][$state_id]['available_actions'][] = 'editshippingdetails';
                }
            }

            // Save changes
            shopWorkflow::setConfig($cfg);
        }
    }
}
