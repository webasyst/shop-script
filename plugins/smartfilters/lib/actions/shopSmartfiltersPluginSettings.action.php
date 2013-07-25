<?php

class shopSmartfiltersPluginSettingsAction extends waViewAction
{
    public function execute()
    {
        $app_settings_model = new waAppSettingsModel();
        $enabled = $app_settings_model->get(array('shop', 'smartfilters'), 'enabled');
        $template = file_get_contents(dirname(__FILE__).'/../../templates/actions/show/Show.html');
        $this->view->assign('template', $template);
        $this->view->assign('enabled', $enabled);
    }
}
