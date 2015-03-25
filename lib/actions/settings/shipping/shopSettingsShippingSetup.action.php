<?php

class shopSettingsShippingSetupAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }
        try {
            $this->view->assign('plugin_id', $plugin_id = waRequest::get('plugin_id'));
            $this->view->assign('plugin', $info = shopShipping::getPluginInfo($plugin_id));
            $params = array(
                'namespace' => "shipping[settings]",
                'value'     => waRequest::post('shipping[settings]'),
            );
            $this->view->assign('settings_html', shopShipping::getPlugin($info['plugin'], $plugin_id)->getSettingsHTML($params));
        } catch (waException $ex) {
            $this->view->assign('error', $ex->getMessage());
        }
    }
}
