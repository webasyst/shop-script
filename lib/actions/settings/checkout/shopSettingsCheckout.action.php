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

    }

}
