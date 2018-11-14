<?php

class shopSettingsCheckoutAction extends shopSettingsCheckoutAbstractAction
{
    public function execute()
    {
        // By default, if there are no storefronts with the old checkout,
        // we redirect to a new first storefront.
        // But it can be disabled by passing the parameter r=1 in GET.
        $do_not_redirect = !waRequest::get('r', null, waRequest::TYPE_INT);
        if (!empty($this->storefronts[2]) && $do_not_redirect) {
            foreach ($this->storefronts[2] as $route) {
                $domain = waIdna::dec($route['domain']);
                $redirect_hash = sprintf('/checkout2&domain=%s&route=%d/', urlencode($domain), urlencode($route['id']));
                break;
            }
        }

        $all_steps = $this->getConfig()->getCheckoutSettings(true);
        $config_steps = $this->getConfig()->getCheckoutSettings();

        $steps = array();

        foreach ($config_steps as $step_id => $step) {
            $steps[$step_id] = $step + $all_steps[$step_id];
            $steps[$step_id]['status'] = 1;
            unset($all_steps[$step_id]);
        }

        foreach ($all_steps as $step_id => $step) {
            $steps[$step_id] = $all_steps[$step_id];
            $steps[$step_id]['status'] = 0;
        }

        $shop_routes = wa()->getRouting()->getByApp('shop');
        $auth_config = $this->getConfig()->getAuth();

        $auth_alert = array();
        foreach ($shop_routes as $domain => $domain_routes) {
            if (!empty($auth_config[$domain]['auth']) && empty($auth_config[$domain]['params']['confirm_email'])) {
                $auth_alert[] = $domain;
            }
        }

        $this->view->assign(array(
            'redirect_hash'    => !empty($redirect_hash) ? $redirect_hash : null,
            'auth_alert'       => $auth_alert,
            'steps'            => $steps,
            'guest_checkout'   => $this->getConfig()->getGeneralSettings('guest_checkout'),
            'shipping_plugins' => $this->getPlugins(shopPluginModel::TYPE_SHIPPING),
            'payment_plugins'  => $this->getPlugins(shopPluginModel::TYPE_PAYMENT),
            'old_storefronts'  => ifempty($this->storefronts, 1, []),
        ));
    }

    protected function getPlugins($type)
    {
        static $model;

        if ($model === null) {
            $model = new shopPluginModel();
        }

        $plugins = $model->listPlugins($type);
        return $plugins;
    }
}
