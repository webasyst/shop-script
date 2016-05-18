<?php
/**
 * @var shopConfig $this
 */


$config = shopWorkflow::getConfig();
$changed = false;
if (!isset($config['actions']['settle']) || !isset($config['actions']['settle']['options']['head'])) {
    if (!empty($this) && ($this instanceof waAppConfig)) {
        $app_config = $this;
    } else {
        $app_config = wa('shop')->getConfig();
    }
    $file = $app_config->getAppsPath('shop', 'lib/config/data/workflow.php');
    if (file_exists($file)) {
        $original_config = include($file);
    } else {
        $original_config = array();
    }

    if (isset($original_config['actions']['settle'])) {
        $config['actions']['settle'] = $original_config['actions']['settle'];
        $changed = true;
    }
}

$internal = array(
    'settle',
    'callback',
    'create',
);

foreach ($internal as $action) {
    if (isset($config['actions'][$action]) && empty($config['actions'][$action]['internal'])) {
        $config['actions'][$action]['internal'] = true;
        $changed = true;
    }
}

if ($changed) {
    shopWorkflow::setConfig($config);
}
