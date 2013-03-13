<?php

class shopSettingsCheckoutOptionsController extends waController
{
    public function execute()
    {
        $step_id = waRequest::post('step_id');
        $class_name = 'shopCheckout'.ucfirst($step_id);

        $config_steps = $this->getConfig()->getCheckoutSettings();
        $step = new $class_name();
        echo $step->getOptions(isset($config_steps[$step_id]) ? $config_steps[$step_id] : array());
    }
}