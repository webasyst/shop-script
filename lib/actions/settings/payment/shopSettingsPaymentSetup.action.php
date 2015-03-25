<?php

class shopSettingsPaymentSetupAction extends waViewAction
{
    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waRightsException(_w('Access denied'));
        }

        $this->view->assign('plugin_id', $plugin_id = waRequest::get('plugin_id'));
        try {

            $this->view->assign('plugin', $info = shopPayment::getPluginInfo($plugin_id));

            $plugin = shopPayment::getPlugin($info['plugin'], $plugin_id);
            $params = array(
                'namespace' => "payment[settings]",
                'value'     => waRequest::post('shipping[settings]'),
            );
            $this->view->assign('settings_html', $plugin->getSettingsHTML($params));
            $this->view->assign('guide_html', $plugin->getGuide($params));

            $model = new shopPluginModel();
            $this->view->assign('shipping', $model->listPlugins(shopPluginModel::TYPE_SHIPPING, array('payment' => $plugin_id, 'all' => true)));
        } catch (waException $ex) {
            $this->view->assign('error', $ex->getMessage());
        }
    }
}
