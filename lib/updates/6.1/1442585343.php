<?php

$files = array(
    'lib/actions/plugins/shopPlugins.action.php',
    'lib/actions/plugins/shopPlugins.controller.php',
    'lib/actions/plugins/shopPluginsSave.controller.php',
    'lib/actions/plugins/shopPluginsSettings.action.php',
    'lib/actions/plugins/shopPluginsSort.controller.php',
    'templates/actions/plugins/Plugins.html',
);

foreach ($files as $f) {
    waFiles::delete($this->getAppPath($f));
}