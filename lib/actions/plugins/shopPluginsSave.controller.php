<?php

class shopPluginsSaveController extends waJsonController
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waException(_w('Access denied'));
        }
        $plugin_id = waRequest::get('id');
        if (!$plugin_id) {
            throw new waException(_ws("Can't save plugin settings: unknown plugin id"));
        }
        $namespace = 'shop_'.$plugin_id;
        /**
         * @var shopPlugin $plugin
         */
        $plugin = waSystem::getInstance()->getPlugin($plugin_id);
        $settings = (array)$this->getRequest()->post($namespace);
        $files = waRequest::file($namespace);
        $settings_defenitions = $plugin->getSettings();
        foreach ($files as $name => $file) {
            if (isset($settings_defenitions[$name])) {
                $settings[$name] = $file;
            }
        }
        try {
            $this->response = $plugin->saveSettings($settings);
            $this->response['message'] = _w('Saved');
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }
    }
}
