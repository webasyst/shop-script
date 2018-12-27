<?php

class shopFrontendShippingPluginController extends waController
{
    public function execute()
    {
        $plugin_id = waRequest::param('plugin_id');
        if (!$plugin_id) {
            throw new waException('Plugin not found', 404);
        }
        
        if (is_numeric($plugin_id)) {
            $plugin = shopShipping::getPlugin(null, $plugin_id);
        } else {
            $plugin = shopShipping::getPlugin($plugin_id, null);
        }
        
        $action = waRequest::param('action_id');
        $method = $action.'Action';
        if (!$action || !method_exists($plugin, $method)) {
            throw new waException('Action not found', 404);
        }

        $plugin->$method();
    }
}
