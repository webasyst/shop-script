<?php
class shopSettingsCheckoutAction extends waViewAction
{
    public function execute()
    {
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

        $this->view->assign('disable_backend_customer_form_validation', wa()->getSetting('disable_backend_customer_form_validation'));
        $this->view->assign('steps', $steps);


        $this->view->assign('guest_checkout', $this->getConfig()->getGeneralSettings('guest_checkout'));

        $shop_routes = wa()->getRouting()->getByApp('shop');
        $auth_config = $this->getConfig()->getAuth();

        $auth_alert = array();
        foreach ($shop_routes as $domain => $domain_routes) {
            if (!empty($auth_config[$domain]['auth']) && empty($auth_config[$domain]['params']['confirm_email'])) {
                $auth_alert[] = $domain;
            }
        }
        $this->view->assign('auth_alert', $auth_alert);
    }
}
