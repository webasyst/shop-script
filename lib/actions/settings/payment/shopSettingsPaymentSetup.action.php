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
            $plugin = shopPayment::getPluginInfo($plugin_id);
            $instance = shopPayment::getPlugin($plugin['plugin'], $plugin_id);

            $params = array(
                'namespace' => "payment[settings]",
                'value'     => waRequest::post('shipping[settings]'),
            );

            $settings_html = $instance->getSettingsHTML($params);
            $guide_html = $instance->getGuide($params);

            $shipping_types = shopShipping::getShippingTypes();

            $payment_types = shopShipping::getShippingPaymentTypes();

            $model = new shopPluginModel();

            $options = array(
                'payment' => $plugin_id, // get available flag
                'all'     => true,       // get all instances of shipping plugins
                'info'    => true,       // fill plugins info
            );

            $shipping = $model->listPlugins(shopPluginModel::TYPE_SHIPPING, $options);

            $this->view->assign(compact('plugin', 'shipping_types', 'payment_types', 'shipping', 'settings_html', 'guide_html'));
        } catch (waException $ex) {
            $this->view->assign('error', $ex->getMessage());
        }
    }
}
