<?php

class shopSettingsCheckoutSaveActions extends waJsonActions
{

    protected function getStepId()
    {
        return waRequest::post('step_id');
    }

    protected function getSteps()
    {
        $file = wa()->getConfig()->getConfigPath('checkout.php', true, 'shop');
        if (file_exists($file)) {
            return include($file);
        }
        return array(
            'contactinfo' => true,
            'shipping' => true,
            'payment' => true,
            'confirmation' => true
        );
    }

    public function guestAction()
    {
        $value = waRequest::post('value');
        $app_settings_model = new waAppSettingsModel();
        if ($value) {
            $app_settings_model->set('shop', 'guest_checkout', $value);
        } else {
            $app_settings_model->del('shop', 'guest_checkout');
        }
    }

    public function toggleAction()
    {
        $step_id = $this->getStepId();
        $data = $this->getSteps();

        if (waRequest::post('status')) {
            $data[$step_id] = true;
        } else {
            if (isset($data[$step_id])) {
                unset($data[$step_id]);
            }
        }
        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set('shop', 'checkout_flow_changed', time());
        $this->save($data);
    }

    public function moveAction()
    {
        $step_id = $this->getStepId();
        $insert_after_id = waRequest::post('prev_id');
        $data = $this->getSteps();

        $step = $data[$step_id];
        unset($data[$step_id]);

        $result = array();
        if (!$insert_after_id) {
            $result = array($step_id => $step);
        }
        foreach ($data as $s_id => $s) {
            $result[$s_id] = $s;
            if ($s_id == $insert_after_id) {
                $result[$step_id] = $step;
            }
        }
        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->set('shop', 'checkout_flow_changed', time());
        $this->save($result);
    }

    public function defaultAction()
    {
        if (waRequest::post()) {
            $step_id = $this->getStepId();
            $name = waRequest::post('name');
            $config = waRequest::post('config', array());
            $data = $this->getSteps();
            if (!is_array($data[$step_id])) {
                $data[$step_id] = array();
            }
            $data[$step_id]['name'] = $name;
            foreach ($config as $k => $v) {
                $data[$step_id][$k] = $v;
            }

            $class = "shopCheckout".ucfirst($step_id);
            $step = new $class();
            $data[$step_id] = $step->setOptions($data[$step_id]);

            $this->save($data);
            $this->response['name'] = $name;
        }
    }

    protected function save($data)
    {
        waUtils::varExportToFile($data, $this->getConfig()->getConfigPath('checkout.php', true, 'shop'));
    }

    protected function backendCustomerFormValidationAction()
    {
        $asm = new waAppSettingsModel();
        $asm->set('shop', 'disable_backend_customer_form_validation', waRequest::post('enable') ? null : '1');
    }
}