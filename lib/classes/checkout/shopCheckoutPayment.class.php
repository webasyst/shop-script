<?php

class shopCheckoutPayment extends shopCheckout
{
    protected $step_id = 'payment';

    public function display()
    {
        $plugin_model = new shopPluginModel();
        $methods = $plugin_model->listPlugins('payment');

        $currencies = wa('shop')->getConfig()->getCurrencies();
        foreach ($methods as $method_id => $m) {
            $plugin = shopPayment::getPlugin($m['plugin'], $m['id']);
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
