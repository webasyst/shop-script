<?php

/**
 * Class shopFrontendCheckoutAction
 * @method shopConfig getConfig()
 */
class shopFrontendCheckoutAction extends waViewAction
{
    protected static $steps = array();

    public function execute()
    {
        $steps = $this->getConfig()->getCheckoutSettings();

        $current_step = waRequest::param('step', waRequest::request('step'));
        if (!$current_step) {
            $current_step = key($steps);
        }

        $title = _w('Checkout');
        if ($current_step == 'success') {
            $order_id = waRequest::get('order_id');
            if (!$order_id) {
                $order_id = wa()->getStorage()->get('shop/order_id');
                $payment_success = false;
            } else {
                $payment_success = true;
                $this->view->assign('payment_success', true);
            }
            if (!$order_id) {
                wa()->getResponse()->redirect(wa()->getRouteUrl('shop/frontend'));
            }
            $order_model = new shopOrderModel();
            $order = $order_model->getById($order_id);
            if (!$payment_success) {
                $order_params_model = new shopOrderParamsModel();
                $order['params'] = $order_params_model->get($order_id);
                $payment = '';
                if (!empty($order['params']['payment_id'])) {
                    try {
                        $plugin = shopPayment::getPlugin(null, $order['params']['payment_id']);
                        $payment = $plugin->payment(waRequest::post(), shopPayment::getOrderData($order, $plugin), null);
                    } catch (waException $ex) {
                        $payment = $ex->getMessage();
                    }
                }
                $order['id'] = shopHelper::encodeOrderId($order_id);
                $order_items_model = new shopOrderItemsModel();
                $order['items'] = $order_items_model->getByField('order_id', $order_id, true);

                $this->getResponse()->addGoogleAnalytics($this->getGoogleAnalytics($order));
            }
            $this->view->assign('order', $order);
            if (isset($payment)) {
                $this->view->assign('payment', $payment);
            }
        } else {
            $cart = new shopCart();
            if (!$cart->count()) {
                $current_step = 'error';
                $this->view->assign('error', _w('Your shopping cart is empty. Please add some products to cart, and then proceed to checkout.'));
            }

            if ($current_step != 'error') {
                if (waRequest::method() == 'post') {
                    if (waRequest::post('wa_auth_login')) {
                        $login_action = new shopLoginAction();
                        $login_action->run();
                    } else {
                        $redirect = false;
                        foreach ($steps as $step_id => $step) {
                            if ($step_id == $current_step) {
                                $step_instance = $this->getStep($step_id);
                                if ($step_instance->execute()) {
                                    $redirect = true;
                                }

                            } elseif ($redirect) {
                                $this->redirect(wa()->getRouteUrl('/frontend/checkout', array('step' => $step_id)));
                            }
                        }

                        // last step
                        if ($redirect) {
                            if ($this->createOrder()) {
                                $this->redirect(wa()->getRouteUrl('/frontend/checkout', array('step' => 'success')));
                            }
                        }
                    }
                } else {
                    $this->view->assign('error', '');
                }
                $title .= ' - '.$steps[$current_step]['name'];
                $steps[$current_step]['content'] = $this->getStep($current_step)->display();
                $this->view->assign('checkout_steps', $steps);
            }
        }
        $this->getResponse()->setTitle($title);
        $this->view->assign('checkout_current_step', $current_step);

        if (waRequest::isXMLHttpRequest()) {
            $this->setThemeTemplate('checkout.'.$current_step.'.html');
        } else {
            $this->setLayout(new shopFrontendLayout());
            $this->setThemeTemplate('checkout.html');
        }
    }

    protected function getGoogleAnalytics($order)
    {
        $title = waRequest::param('title');
        if (!$title) {
            $title = $this->getConfig()->getGeneralSettings('name');
        }
        if (!$title) {
            $app = wa()->getAppInfo();
            $title = $app['name'];
        }

        $result =  "_gaq.push(['_addTrans',
            '".$order['id']."',           // transaction ID - required
            '".htmlspecialchars($title)."',  // affiliation or store name
            '".$this->getBasePrice($order['total'], $order['currency'])."',          // total - required
            '".$this->getBasePrice($order['tax'], $order['currency'])."',           // tax
            '".$this->getBasePrice($order['shipping'], $order['currency'])."',              // shipping
            '".$this->getOrderAddressField($order, 'city')."',       // city
            '".$this->getOrderAddressField($order, 'region')."',     // state or province
            '".$this->getOrderAddressField($order, 'country')."'             // country
        ]);\n";

        foreach ($order['items'] as $item) {
            $sku = $item['type'] == 'product' ? $item['sku_code'] : '';
            $result .= " _gaq.push(['_addItem',
            '".$order['id']."',           // transaction ID - required
            '".$sku."',           // SKU/code - required
            '".htmlspecialchars($item['name'])."',        // product name
            '',   // category or variation
            '".$this->getBasePrice($item['price'], $order['currency'])."',          // unit price - required
            '".$item['quantity']."'               // quantity - required
          ]);\n";
        }

        $result .= "_gaq.push(['_trackTrans']);\n";

        return $result;
    }

    protected function getOrderAddressField($order, $name)
    {
        if (isset($order['params']['shipping_address.'.$name])) {
            return htmlspecialchars($order['params']['shipping_address.'.$name]);
        }
        return '';
    }

    protected function getBasePrice($price, $currency)
    {
        return shop_currency($price, $currency, $this->getConfig()->getCurrency(true), false);
    }


    protected function createOrder()
    {
        $checkout_data = $this->getStorage()->get('shop/checkout');

        $contact = $this->getUser()->isAuth() ? $this->getUser() : $checkout_data['contact'];
        $cart = new shopCart();
        $items = $cart->items(false);
        // remove id from item
        foreach ($items as &$item) {
            unset($item['id']);
            unset($item['parent_id']);
        }
        unset($item);

        $order = array(
            'contact' => $contact,
            'items'   => $items,
            'total'   => $cart->total(false),
            'params'  => isset($checkout_data['params']) ? $checkout_data['params'] : array(),
        );

        $order['discount'] = shopDiscounts::apply($order);

        if (isset($checkout_data['shipping'])) {
            $order['params']['shipping_id'] = $checkout_data['shipping']['id'];
            $order['params']['shipping_rate_id'] = $checkout_data['shipping']['rate_id'];
            $shipping_step = new shopCheckoutShipping();
            $rate = $shipping_step->getRate($order['params']['shipping_id'], $order['params']['shipping_rate_id']);
            $order['params']['shipping_plugin'] = $rate['plugin'];
            $order['params']['shipping_name'] = $rate['name'];
            if (isset($rate['est_delivery'])) {
                $order['params']['shipping_est_delivery'] = $rate['est_delivery'];
            }
            if (!isset($order['shipping'])) {
                $order['shipping'] = $rate['rate'];
            }
            if (!empty($order['params']['shipping'])) {
                foreach ($order['params']['shipping'] as $k => $v) {
                    $order['params']['shipping_params_'.$k] = $v;
                }
                unset($order['params']['shipping']);
            }
        } else {
            $order['shipping'] = 0;
        }

        if (isset($checkout_data['payment'])) {
            $order['params']['payment_id'] = $checkout_data['payment'];
            $plugin_model = new shopPluginModel();
            $plugin_info = $plugin_model->getById($checkout_data['payment']);
            $order['params']['payment_name'] = $plugin_info['name'];
            $order['params']['payment_plugin'] = $plugin_info['plugin'];
            if (!empty($order['params']['payment'])) {
                foreach ($order['params']['payment'] as $k => $v) {
                    $order['params']['payment_params_'.$k] = $v;
                }
                unset($order['params']['payment']);
            }
        }

        if ($skock_id = waRequest::post('stock_id')) {
            $order['params']['stock_id'] = $skock_id;
        }

        $routing_url = wa()->getRouting()->getRootUrl();
        $order['params']['storefront'] = wa()->getConfig()->getDomain().($routing_url ? '/'.$routing_url : '');

        if ($ref = wa()->getStorage()->get('shop/referer')) {
            $order['params']['referer'] = $ref;
            $ref_parts = parse_url($ref);
            $order['params']['referer_host'] = $ref_parts['host'];
        }

        $order['params']['ip'] = waRequest::getIp();
        $order['params']['user_agent'] = waRequest::getUserAgent();

        foreach (array('shipping', 'billing') as $ext) {
            $address = $contact->getFirst('address.'.$ext);
            if (!$address) {
                $address = $contact->getFirst('address');
            }
            if ($address) {
                foreach ($address['data'] as $k => $v) {
                    $order['params'][$ext.'_address.'.$k] = $v;
                }
            }
        }

        if (isset($checkout_data['comment'])) {
            $order['comment'] = $checkout_data['comment'];
        }

        $workflow = new shopWorkflow();
        if ($order_id = $workflow->getActionById('create')->run($order)) {
            $cart->clear();
            wa()->getStorage()->remove('shop/checkout');
            wa()->getStorage()->set('shop/order_id', $order_id);
            return true;
        }
    }

    /**
     * @param string $step_id
     * @return shopCheckout
     */
    protected function getStep($step_id)
    {
        if (!isset(self::$steps[$step_id])) {
            $class_name = 'shopCheckout'.ucfirst($step_id);
            self::$steps[$step_id] = new $class_name();
        }
        return self::$steps[$step_id];
    }

}
