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

            $taxes_mode = new shopTaxModel();
            $this->view->assign('taxes', $taxes_mode->getAll());
            $params = array(
                'namespace' => "shipping[settings]",
                'value'     => waRequest::post('shipping[settings]'),
            );
            $instance = shopShipping::getPlugin($info['plugin'], $plugin_id);
            $guide_html = method_exists($instance, 'getGuide') ? $instance->getGuide($params) : '';
            $this->view->assign('settings_html', $instance->getSettingsHTML($params));
            $this->view->assign('guide_html', $guide_html);
        } catch (waException $ex) {
            $this->view->assign('error', $ex->getMessage());
        }
    }
}
