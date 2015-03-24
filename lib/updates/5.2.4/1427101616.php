<?php

$icons_map = array_fill_keys(array(
    'new',
    'processing',
    'paid',
    'sent',
    'completed',
    'refunded',
    'trash',
    'flag-white',
    'flag-blue',
    'flag-yellow',
    'flag-green',
    'flag-red',
    'flag-purple',
    'flag-black',
    'flag-checkers'
), true);

$shop_workflow = new shopWorkflow();
$change = false;
$config = shopWorkflow::getConfig();
foreach ($shop_workflow->getAvailableActions() as $action_id => $action) {
    if (!$action['original'] && !empty($action['options']['position']) && !empty($action['options']['icon'])) {
        $i = $action['options']['icon'];
        if (!empty($icons_map[$i])) {
            $config['actions'][$action_id]['options']['icon'] = 'ss ' . $i;
            $change = true;
        }
    }
}

if ($change) {
    shopWorkflow::setConfig($config);
}