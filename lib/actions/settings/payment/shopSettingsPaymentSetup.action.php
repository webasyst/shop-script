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
            if (empty($plugin['id']) && $instance instanceof shopPaymentDummy && wa()->isSingleAppMode()) {
                throw new waRightsException(_w('Access denied'));
            }

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
            if (!is_numeric($plugin_id) && !empty($plugin['info']['pos_initiates_payment'])) {
                foreach($shipping as &$m) {
                    // by default disable all shipping options when adding a new payment plugin with pos_initiates_payment
                    $m['available'] = false;
                }
                unset($m);
            }

            $storefronts = shopStorefrontList::getAllStorefronts(true);
            foreach ($storefronts as &$s) {
                $enabled_payment_ids = ifset($s, 'route', 'payment_id', null);
                $s['force_enabled'] = !$enabled_payment_ids;
                if (!empty($plugin['id']) && is_array($enabled_payment_ids) && array_values($enabled_payment_ids) == [$plugin['id']]) {
                    $s['force_enabled'] = true; // last payment plugin can not be disabled
                }
                $s['is_enabled'] = empty($plugin['id']) || !$enabled_payment_ids || in_array($plugin['id'], $enabled_payment_ids);
                $s['route_url'] = ifset($s, 'route', 'url', null);
            }
            unset($s);

            $this->view->assign(compact('plugin', 'shipping_types', 'payment_types', 'shipping', 'settings_html', 'guide_html', 'storefronts'));
        } catch (waException $ex) {
            $this->view->assign('error', $ex->getMessage());
        }
    }
}
