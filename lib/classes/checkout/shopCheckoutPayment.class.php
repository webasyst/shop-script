<?php

class shopCheckoutPayment extends shopCheckout
{
    protected $step_id = 'payment';

    public function display()
    {
        $plugin_model = new shopPluginModel();

        if (waRequest::param('payment_id') && is_array(waRequest::param('payment_id'))) {
            $methods = $plugin_model->getById(waRequest::param('payment_id'));
        } else {
            $methods = $plugin_model->listPlugins('payment');
        }

        $shipping = $this->getSessionData('shipping');
        if ($shipping) {
            $disabled = shopHelper::getDisabledMethods('payment', $shipping['id']);
        } else {
            $disabled = array();
        }

        $currencies = wa('shop')->getConfig()->getCurrencies();
        foreach ($methods as $method_id => $m) {
            if (in_array($method_id, $disabled)) {
                unset($methods[$method_id]);
                continue;
            }
            $plugin = shopPayment::getPlugin($m['plugin'], $m['id']);
            $plugin_info = $plugin->info($m['plugin']);
            $methods[$method_id]['icon'] = $plugin_info['icon'];
            $allowed_currencies = $plugin->allowedCurrency();
            if ($allowed_currencies !== true) {
                $allowed_currencies = (array) $allowed_currencies;
                if (!array_intersect($allowed_currencies, array_keys($currencies))) {
                    $methods[$method_id]['error'] = sprintf(_w('Payment procedure cannot be processed because required currency %s is not defined in your store settings.'), implode(', ', $allowed_currencies));
                }
            }
        }

        $view = wa()->getView();
        $view->assign('checkout_payment_methods', $methods);
        $m = reset($methods);
        $view->assign('payment_id', $this->getSessionData('payment', $m ? $m['id'] : null));
    }


    public function validate()
    {

    }

    public function execute()
    {
        if ($payment_id = waRequest::post('payment_id')) {
            $this->setSessionData('payment', $payment_id);
            if ($comment = waRequest::post('comment')) {
                $this->setSessionData('comment', $comment);
            }
            return true;
        } else {
            return false;
        }

    }
}
