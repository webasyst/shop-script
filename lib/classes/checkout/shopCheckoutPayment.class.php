<?php

class shopCheckoutPayment extends shopCheckout
{
    protected $step_id = self::STEP_PAYMENT;

    public function display()
    {
        $methods = $this->getPlugins();
        $shop_config = wa('shop')->getConfig();
        /**
         * @var shopConfig $shop_config
         */

        $currencies = $shop_config->getCurrencies();
        $selected = null;
        foreach ($methods as $key => $m) {

            if (!empty($m['available'])) {

                $method_id = $m['id'];
                try {
                    $plugin = shopPayment::getPlugin($m['plugin'], $m['id']);
                    $plugin_info = $plugin->info($m['plugin']);
                    $methods[$key]['icon'] = ifset($plugin_info, 'icon', null);
                    $custom_fields = $this->getCustomFields($method_id, $plugin);
                    $custom_html = '';
                    foreach ($custom_fields as $c) {
                        $custom_html .= '<div class="wa-field">'.$c.'</div>';
                    }
                    $methods[$key]['custom_html'] = $custom_html;
                    $allowed_currencies = $plugin->allowedCurrency();
                    if ($allowed_currencies !== true) {
                        $allowed_currencies = (array)$allowed_currencies;
                        if (!array_intersect($allowed_currencies, array_keys($currencies))) {
                            $format = _w('Payment procedure cannot be processed because required currency %s is not defined in your store settings.');
                            $methods[$key]['error'] = sprintf($format, implode(', ', $allowed_currencies));
                        }
                    }
                    if (!$selected && empty($methods[$key]['error'])) {
                        $selected = $method_id;
                    }
                } catch (waException $ex) {
                    waLog::log($ex->getMessage(), 'shop/checkout.error.log');
                }
            } else {
                $methods[$key]['error'] = _w('Not available');
                unset($methods[$key]);
            }
        }

        $this->assign('checkout_payment_methods', $methods);
        $this->assign('payment_id', $this->getSessionData('payment', $selected));

        $this->addFlowStep();
    }

    protected function getPlugins($id = null)
    {
        $options = array();

        # filter enabled at frontend plugins
        $payment_id = waRequest::param('payment_id');
        if ($payment_id && is_array($payment_id)) {
            $options['id'] = $payment_id;
        }
        if ($id) {
            if (empty($options['id'])) {
                $options['id'] = $id;
            } elseif (in_array($id, $options['id'])) {
                $options['id'] = $id;
            } else {
                return array();
            }
        }

        # filter applicable payment plugins
        if ($shipping = $this->getSessionData('shipping')) {
            if (self::getStepNumber($this->step_id) > self::getStepNumber(self::STEP_SHIPPING)) {
                $options[shopPluginModel::TYPE_SHIPPING] = $shipping['id'];
            }
        }

        return $this->plugin_model->listPlugins(shopPluginModel::TYPE_PAYMENT, $options);
    }

    protected function getCustomFields($id, waPayment $plugin)
    {
        $contact = $this->getContact();
        $order_params = $this->getSessionData('params', array());
        $payment_params = isset($order_params['payment']) ? $order_params['payment'] : array();
        foreach ($payment_params as $k => $v) {
            $order_params['payment_params_'.$k] = $v;
        }
        $order = new waOrder(array(
            'contact'    => $contact,
            'contact_id' => $contact ? $contact->getId() : null,
            'params'     => $order_params,
        ));
        $custom_fields = $plugin->customFields($order);
        if (!$custom_fields) {
            return $custom_fields;
        }

        $selected = ($id == $this->getSessionData('payment'));

        if ($selected) {
            foreach ($custom_fields as $name => &$row) {
                if (isset($payment_params[$name])) {
                    $row['value'] = $payment_params[$name];
                }
                unset($row);
            }
        }
        return $this->getControls($custom_fields, 'payment_'.$id);
    }


    public function getErrors()
    {
        $errors = array();
        $payment_id = $this->getSessionData('payment');
        if (!$payment_id) {
            $errors[] = _w('Payment option is not defined. Please return to the payment selection checkout step to continue.');
        } elseif ($shipping = $this->getSessionData('shipping')) {
            $disabled = shopHelper::getDisabledMethods('payment', $shipping['id']);
            if (in_array($payment_id, $disabled)) {
                //TODO change translate payment option not applicable
                $errors[] = _w('Payment option is not defined. Please return to the payment selection checkout step to continue.');
            }
        }
        return $errors;
    }

    public function execute()
    {
        if ($payment_id = waRequest::post('payment_id', null, waRequest::TYPE_INT)) {

            $plugins = $this->getPlugins($payment_id);
            if ($plugins) {
                $plugin_info = reset($plugins);
                if (empty($plugin_info['available'])) {
                    return false;
                }
                $this->setSessionData('payment', $payment_id);
                if ($comment = waRequest::post('comment', null, waRequest::TYPE_STRING_TRIM)) {
                    $this->setSessionData('comment', $comment);
                }
                if ($payment_params = waRequest::post('payment_'.$payment_id)) {
                    $params = $this->getSessionData('params', array());
                    $params['payment'] = $payment_params;
                    $this->setSessionData('params', $params);
                }
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
