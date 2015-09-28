<?php

class shopRedirectPluginSettingsAction extends waViewAction
{

    public function execute()
    {

        $plugin_id = 'redirect';
        $vars = array();

        /**
         * @var shopPlugin $plugin
         */
        $plugin = waSystem::getInstance()->getPlugin($plugin_id, true);
        $namespace = wa()->getApp().'_'.$plugin_id;

        $params = array();
        $params['id'] = $plugin_id;
        $params['namespace'] = $namespace;
        $params['title_wrapper'] = '%s';
        $params['description_wrapper'] = '<br><span class="hint">%s</span>';
        $params['control_wrapper'] = '<div class="name">%s</div><div class="value">%s %s</div>';

        $settings_controls = $plugin->getControls($params);
        $this->getResponse()->setTitle(_w(sprintf('Plugin %s settings', $plugin->getName())));

        $vars['plugin_info'] = array(
            'name' => $plugin->getName()
        );
        $vars['plugin_id'] = $plugin_id;
        $vars['settings_controls'] = $settings_controls;

        $this->view->assign($vars);
    }
}
