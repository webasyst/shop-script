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
        end($steps);
        $last_step = key($steps);
        reset($steps);

        $current_step = waRequest::request('step', waRequest::param('step'));
        if (!$current_step) {
            $current_step = key($steps);
        }

        $is_post = (waRequest::method() == 'post');
        $is_last_step = ($current_step === $last_step);

        $title = _w('Checkout');
        if ($current_step == shopCheckout::STEP_SUCCESS) {
            $this->success();
        } elseif ($this->verify($current_step)) {
            if ($is_post) {

                // checkout auth
                if (waRequest::post('wa_auth_login')) {
                    $login_action = new shopLoginAction();
                    $login_action->run();
                } else {
                    $errors = array();
                    $redirect = null;

                    foreach ($steps as $step_id => $step) {
                        $step_instance = $this->getStep($step_id);

                        if ($step_id == $current_step) {
                            $redirect = $step_instance->execute();
                        } elseif ($redirect && empty($errors)) {
                            $this->redirect(wa()->getRouteUrl('/frontend/checkout', array('step' => $step_id)));
                        } elseif ($redirect === false) {
                            break;
                        }
                        if ($e = $step_instance->getErrors()) {
                            $errors = array_merge($errors, $e);
                        }
                    }

                    // last step
                    if ($errors) {
                        $this->view->assign('error', implode('<br>', $errors));
                    } elseif ($redirect && $is_last_step) {
                        if ($order_id = $this->createOrder($errors)) {
                            wa()->getStorage()->set('shop/success_order_id', $order_id);
                            $this->redirect(wa()->getRouteUrl('/frontend/checkout', array('step' => shopCheckout::STEP_SUCCESS)));
                        } else {
                            $current_step = shopCheckout::STEP_ERROR;
                            $errors[] = '&larr; <a href="'.wa()->getRouteUrl('/frontend/cart').'">'._w('Go to shopping cart').'</a>';
                            $this->view->assign('error', implode('<br><br>', $errors));
                        }
                    }


                }
            } else {
                $this->view->assign('error', '');
            }

            if ($current_step != shopCheckout::STEP_ERROR) {
                if (empty($steps[$current_step])) {
                    throw new waException(_ws('Page not found'), 404);
                }
                $title .= ' - '.$steps[$current_step]['name'];
                $steps[$current_step]['content'] = $this->getStep($current_step)->display();
                $this->view->assign('checkout_steps', $steps);
            }
        }
        $this->getResponse()->setTitle($title);
        $this->view->assign('checkout_current_step', $current_step);

        /**
         * @event frontend_checkout
         * @return array[string]string $return[%plugin_id%] html output
         */
        $event_params = array('step' => $current_step);

        $order_id = waRequest::get('order_id');
        $transaction_id = waRequest::get('transaction_id');
        if (!empty($transaction_id) && !empty($order_id) && $current_step === shopCheckout::STEP_ERROR) {
            $transaction_model = new waTransactionModel();
            $transaction = $transaction_model->getByField(['id' => $transaction_id, 'order_id' => $order_id]);
            if (!empty($transaction)) {
                $error_message = ifempty($transaction['error'], _w('Payment was canceled.'));
                $this->view->assign('error', $error_message);
            }
        }

        $this->view->assign('frontend_checkout', wa()->event('frontend_checkout', $event_params));

        if (waRequest::isXMLHttpRequest()) {
            $this->setThemeTemplate('checkout.'.$current_step.'.html');
        } else {
            $this->setLayout(new shopFrontendLayout());
            $this->setThemeTemplate('checkout.html');
        }
    }

    protected function verify(&$current_step)
    {
        $cart = new shopCart();
        if (!$cart->count()
            &&
            $current_step != shopCheckout::STEP_ERROR
            &&
            ($current_step != shopCheckout::STEP_CONFIRMATION || !waRequest::get('terms'))
        ) {
            $current_step = shopCheckout::STEP_ERROR;
            $this->view->assign('error', _w('Your shopping cart is empty. Please add some products to cart, and then proceed to checkout.'));
        }
        return $current_step != shopCheckout::STEP_ERROR;
    }

    protected function success()
    {
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
        if ($order) {
            $order['_id'] = $order['id'];
        }
        if (!$payment_success) {
            $order_params_model = new shopOrderParamsModel();
            $order['params'] = $order_params_model->get($order_id);
            $order_items_model = new shopOrderItemsModel();
            $order['items'] = $order_items_model->getByField('order_id', $order_id, true);
            shopOrderItemsModel::sortItemsByGeneralSettings($order['items']);

            $payment = '';
            if (!empty($order['params']['payment_id'])) { # order has related payment plugin
                $workflow = new shopWorkflow();
                /** @var shopWorkflowState $state */
                $state = $workflow->getStateById($order['state_id']);
                if ($state->paymentAllowed()) { # order state allow payment
                    try {
                        /** @var waPayment $plugin */
                        $plugin = shopPayment::getPlugin(null, $order['params']['payment_id']);
                        $route = wa()->getRouting()->getRoute();
                        if (2 == ifset($route, 'checkout_version', null)) {
                            $checkout_config = new shopCheckoutConfig($route['checkout_storefront_id']);
                            $auto_submit = isset($checkout_config['confirmation']['auto_submit']) ? $checkout_config['confirmation']['auto_submit'] : true;
                        } else {
                            $auto_submit = true;
                        }
                        $payment = $plugin->payment(waRequest::post(), shopPayment::getOrderData($order, $plugin), $auto_submit);
                    } catch (waException $ex) {
                        $payment = $ex->getMessage();
                    }
                } else {
                    $payment = $state->paymentNotAllowedText();
                }
            }
            $order['id'] = shopHelper::encodeOrderId($order_id);
            $this->addGoogleAnalytics($order, $order_id);
        } else {
            $order['id'] = shopHelper::encodeOrderId($order_id);
        }
        $this->view->assign('order', $order);
        if (isset($payment)) {
            $this->view->assign('payment', $payment);
        }

        // Header and message depend on current used checkout version
        $route = wa()->getRouting()->getRoute();
        $this->view->assign('my_order_url', wa()->getRouteUrl('shop/frontend/myOrder', ['id' => $order['_id']]));
        if (2 == ifset($route, 'checkout_version', null)) {
            $checkout_config = new shopCheckoutConfig(ifset($route, 'checkout_storefront_id', null));
            $this->view->assign([
                'thankyou_header' => $checkout_config['confirmation']['thankyou_header'],
                'thankyou_content' => $checkout_config['confirmation']['thankyou_content'],
            ]);
        } else {
            $this->view->assign([
                'thankyou_header' =>_w('Thank you!'),
                'thankyou_content' => _w('We successfully accepted your order, and will contact you asap.')." "._w('Your order number is ').' $order_id',
            ]);
        }

    }

    protected function addGoogleAnalytics($order, $order_id)
    {

        if (wa()->getStorage()->get('shop/success_order_id') == $order_id) {
            $domain = wa()->getRouting()->getDomain(null, true);
            $domain_config_path = $this->getConfig()->getConfigPath('domains/'.$domain.'.php', true, 'site');
            if (file_exists($domain_config_path)) {
                /**
                 * @var $domain_config array
                 */
                $domain_config = include($domain_config_path);
                // if google analytics
                if (isset($domain_config['google_analytics']) && !is_array($domain_config['google_analytics'])) {
                    $domain_config['google_analytics'] = array(
                        'code' => $domain_config['google_analytics'],
                    );
                }
                if (!empty($domain_config['google_analytics']['code'])) {
                    $this->getResponse()->addGoogleAnalytics(
                        $this->getGoogleAnalytics($order, !empty($domain_config['google_analytics']['universal']))
                    );
                }
            }
            // to show ga code only once
            wa()->getStorage()->del('shop/success_order_id');
        }
    }


    protected function getGoogleAnalytics($order, $universal = false)
    {
        $title = waRequest::param('title');
        if (!$title) {
            $title = $this->getConfig()->getGeneralSettings('name');
        }
        if (!$title) {
            $app = wa()->getAppInfo();
            $title = $app['name'];
        }

        if ($universal) {
            $result = "ga('require', 'ecommerce', 'ecommerce.js');\n";
            $result .= "ga('ecommerce:addTransaction', {
                'id': '".$order['id']."',           // transaction ID - required
                'affiliation': '".htmlspecialchars($title, ENT_QUOTES, 'utf-8')."',  // affiliation or store name
                'revenue': '".$this->formatPrice($order['total'])."',          // total - required
                'shipping': '".$this->formatPrice($order['shipping'])."',              // shipping
                'tax': '".$this->formatPrice($order['tax'])."',           // tax
                'currency': '".$order['currency']."' // currency
            });\n";

            foreach ($order['items'] as $item) {
                $sku = $item['type'] == 'product' ? $item['sku_code'] : '';
                $result .= "ga('ecommerce:addItem', {
                'id': '".$order['id']."',           // transaction ID - required
                'name': '".htmlspecialchars($item['name'], ENT_QUOTES, 'utf-8')."',        // product name
                'sku': '".$sku."',           // SKU/code - required
                'category': '',   // category or variation
                'price': '".$this->formatPrice($item['price'])."',          // unit price - required
                'quantity': '".$item['quantity']."'               // quantity - required
              });\n";
            }
            $result .= "ga('ecommerce:send');\n";

        } else {
            $items = '';
            foreach ($order['items'] as $item) {
                $sku = $item['type'] == 'product' ? $item['sku_code'] : '';
                $items = "{
                    item_id: '{$order['id']}',
                    item_name: '".htmlspecialchars($item['name'], ENT_QUOTES, 'utf-8')."',
                    price: {$this->formatPrice($item['price'])},
                    quantity: {$item['quantity']},
                    item_variant: '$sku'
                },\n";
            }

            $result = "gtag('event', 'purchase', {
                transaction_id: '{$order['id']}', // transaction ID - required
                value: {$this->formatPrice($order['total'])}, // total - required
                currency: '{$order['currency']}', // currency - required
                tax: {$this->formatPrice($order['tax'])}, // tax
                shipping: {$this->formatPrice($order['shipping'])}, // shipping
                affiliation: '".htmlspecialchars($title, ENT_QUOTES, 'utf-8')."', // affiliation or store name
                items: [$items]
            });";
        }
        return $result;
    }

    protected function getOrderAddressField($order, $name)
    {
        if (isset($order['params']['shipping_address.'.$name])) {
            return htmlspecialchars($order['params']['shipping_address.'.$name]);
        }
        return '';
    }

    protected function formatPrice($price)
    {
        return str_replace(',', '.', (float)$price);
    }

    protected function getBasePrice($price, $currency)
    {
        return shop_currency($price, $currency, $this->getConfig()->getCurrency(true), false);
    }


    protected function createOrder(&$errors = array())
    {
        $cart = new shopCart();
        if (!wa()->getSetting('ignore_stock_count')) {
            $check_count = true;
            if (wa()->getSetting('limit_main_stock') && waRequest::param('stock_id')) {
                $check_count = waRequest::param('stock_id');
            }
            $cart_model = new shopCartItemsModel();
            $not_available_items = $cart_model->getNotAvailableProducts($cart->getCode(), $check_count);
            shopFrontendCartAction::validateNotAvailableProducts($not_available_items, $errors);
            if ($errors) {
                return false;
            }
        }

        $checkout_data = $this->getStorage()->get('shop/checkout');

        if ($this->getUser()->isAuth()) {
            $contact = $this->getUser();
        } elseif (!empty($checkout_data['contact']) && $checkout_data['contact'] instanceof waContact) {
            $contact = $checkout_data['contact'];
        } else {
            $contact = new waContact();
        }

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

        $order['discount_description'] = null;
        $order['discount'] = shopDiscounts::apply($order, $order['discount_description']);

        $this->parseShippingParams($order, $checkout_data);

        if (isset($checkout_data['payment'])) {
            $order['params']['payment_id'] = (int)$checkout_data['payment'];
            $plugin_model = new shopPluginModel();
            $plugin_info = $plugin_model->getById($checkout_data['payment']);
            if ($plugin_info && ($plugin_info['type'] == shopPluginModel::TYPE_PAYMENT)) {
                $order['params']['payment_name'] = $plugin_info['name'];
                $order['params']['payment_plugin'] = $plugin_info['plugin'];
                if (!empty($order['params']['payment'])) {
                    foreach ($order['params']['payment'] as $k => $v) {
                        $order['params']['payment_params_'.$k] = $v;
                    }
                }
            } else {
                $order['params']['payment_name'] = '';
                $order['params']['payment_plugin'] = null;
            }
            unset($order['params']['payment']);
        }

        $routing_url = wa()->getRouting()->getRootUrl();
        $order['params']['storefront'] = wa()->getConfig()->getDomain().($routing_url ? '/'.$routing_url : '');
        if (wa()->getStorage()->get('shop_order_buybutton')) {
            $order['params']['sales_channel'] = 'buy_button:';
        }

        if (($ref = waRequest::cookie('referer'))) {
            $order['params']['referer'] = $ref;
            $ref_parts = @parse_url($ref);
            $order['params']['referer_host'] = $ref_parts['host'];
            // try get search keywords
            if (!empty($ref_parts['query'])) {
                $search_engines = array(
                    'text' => 'yandex\.|rambler\.',
                    'q'    => 'bing\.com|mail\.|google\.',
                    's'    => 'nigma\.ru',
                    'p'    => 'yahoo\.com',
                );
                $q_var = false;
                foreach ($search_engines as $q => $pattern) {
                    if (preg_match('/('.$pattern.')/si', $ref_parts['host'])) {
                        $q_var = $q;
                        break;
                    }
                }
                // default query var name
                if (!$q_var) {
                    $q_var = 'q';
                }
                parse_str($ref_parts['query'], $query);
                if (!empty($query[$q_var])) {
                    $order['params']['keyword'] = $query[$q_var];
                }
            }
        }

        if (($utm = waRequest::cookie('utm'))) {
            $utm = json_decode($utm, true);
            if ($utm && is_array($utm)) {
                foreach ($utm as $k => $v) {
                    $order['params']['utm_'.$k] = $v;
                }
            }
        }

        if (($landing = waRequest::cookie('landing')) && ($landing = @parse_url($landing))) {
            if (!empty($landing['query'])) {
                @parse_str($landing['query'], $arr);
                if (!empty($arr['gclid'])
                    && !empty($order['params']['referer_host'])
                    && strpos($order['params']['referer_host'], 'google') !== false
                ) {
                    $order['params']['referer_host'] .= ' (cpc)';
                    $order['params']['cpc'] = 1;
                } elseif (!empty($arr['_openstat'])
                    && !empty($order['params']['referer_host'])
                    && strpos($order['params']['referer_host'], 'yandex') !== false
                ) {
                    $order['params']['referer_host'] .= ' (cpc)';
                    $order['params']['openstat'] = $arr['_openstat'];
                    $order['params']['cpc'] = 1;
                }
            }

            $order['params']['landing'] = $landing['path'];
        }

        // A/B tests
        $abtest_variants_model = new shopAbtestVariantsModel();
        foreach (waRequest::cookie() as $k => $v) {
            if (substr($k, 0, 5) == 'waabt') {
                $variant_id = $v;
                $abtest_id = substr($k, 5);
                if (wa_is_int($abtest_id) && wa_is_int($variant_id)) {
                    $row = $abtest_variants_model->getById($variant_id);
                    if ($row && $row['abtest_id'] == $abtest_id) {
                        $order['params']['abt'.$abtest_id] = $variant_id;
                    }
                }
            }
        }

        $order['params']['ip'] = waRequest::getIp();
        $order['params']['user_agent'] = waRequest::getUserAgent();

        foreach (array('shipping', 'billing') as $ext) {
            $address = $contact->getFirst('address.'.$ext);
            if ($address) {
                foreach ($address['data'] as $k => $v) {
                    $order['params'][$ext.'_address.'.$k] = $v;
                }
            }
        }

        if (isset($checkout_data['comment'])) {
            $order['comment'] = $checkout_data['comment'];
        }

        list($stock_id, $virtualstock_id) = self::determineStockIds($order);
        if ($virtualstock_id) {
            $order['params']['virtualstock_id'] = $virtualstock_id;
        }
        if ($stock_id) {
            $order['params']['stock_id'] = $stock_id;
        }

        $workflow = new shopWorkflow();
        if ($order_id = $workflow->getActionById('create')->run($order)) {

            $step_number = shopCheckout::getStepNumber();
            $checkout_flow = new shopCheckoutFlowModel();
            $checkout_flow->add(array(
                'step' => $step_number,
            ));

            $cart->clear();
            wa()->getStorage()->remove('shop/checkout');
            wa()->getStorage()->set('shop/order_id', $order_id);

            return $order_id;
        } else {
            return false;
        }
    }

    public static function determineStockIds($order)
    {
        $stock_rules_model = new shopStockRulesModel();
        $rules = $stock_rules_model->getRules();
        $stocks = shopHelper::getStocks();

        /**
         * @event frontend_checkout_stock_rules
         *
         * Hook allows to implement custom rules to automatically select stock for new orders.
         *
         * $params['rules'] is a list of rules from `shop_stock_rules` table.
         * Plugins are expected to modify items in $params['rules'] by creating 'fulfilled' key (boolean)
         * for rule types plugin is responsible for.
         *
         * See also `backend_settings_stocks` event for how to set up settings form for such rules.
         *
         * @param array $params
         * @param array [array] $params['order'] order data
         * @param array [array] $params['rules'] list of rules to modify.
         * @param array [array] $params['stocks'] same as shopHelper::getStocks()
         * @return null
         */
        $event_params = array(
            'order'  => $order,
            'stocks' => $stocks,
            'rules'  => &$rules,
        );
        self::processBuiltInRules($event_params);
        wa('shop')->event('frontend_checkout_stock_rules', $event_params);

        $groups = $stock_rules_model->prepareRuleGroups($rules);
        foreach ($groups as $g) {
            if (($g['stock_id'] && empty($stocks[$g['stock_id']])) || ($g['virtualstock_id'] && empty($stocks['v'.$g['virtualstock_id']]))) {
                continue;
            }

            $all_fulfilled = true;
            foreach ($g['conditions'] as $rule) {
                if (!ifset($rule['fulfilled'], false)) {
                    $all_fulfilled = false;
                    break;
                }
            }
            if ($all_fulfilled) {
                return array($g['stock_id'], $g['virtualstock_id']);
            }
        }

        // No rule matched the order. Use stock specified in routing params.
        $virtualstock_id = null;
        $stock_id = waRequest::param('stock_id', null, 'string');
        if (empty($stocks[$stock_id])) {
            $stock_id = null;
        } elseif (isset($stocks[$stock_id]['substocks'])) {
            $virtualstock_id = $stocks[$stock_id]['id'];
            $stock_id = null;
        }
        return array($stock_id, $virtualstock_id);
    }

    protected static function processBuiltInRules(&$params)
    {
        $shipping_type_id = null;
        if (!empty($params['order']['params']['shipping_id'])) {
            $shipping_type_id = $params['order']['params']['shipping_id'];
        }
        $shipping_country = $shipping_region = null;
        if (!empty($params['order']['params']['shipping_address.country'])) {
            $shipping_country = (string)$params['order']['params']['shipping_address.country'];
            if (!empty($params['order']['params']['shipping_address.region'])) {
                $shipping_region = $shipping_country.':'.$params['order']['params']['shipping_address.region'];
            }
        }

        foreach ($params['rules'] as &$rule) {
            if ($rule['rule_type'] == 'by_shipping') {
                $rule['fulfilled'] = $shipping_type_id && $shipping_type_id == $rule['rule_data'];
            } elseif ($rule['rule_type'] == 'by_region') {
                $rule['fulfilled'] = false;
                foreach (explode(',', $rule['rule_data']) as $candidate) {
                    if ($candidate === $shipping_country || $candidate === $shipping_region) {
                        $rule['fulfilled'] = true;
                        break;
                    }
                }
            }
        }
        unset($rule);
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

    protected function parseShippingParams(&$order, $checkout_data)
    {
        if (isset($checkout_data['shipping'])) {
            $order['params']['shipping_id'] = $checkout_data['shipping']['id'];
            $order['params']['shipping_rate_id'] = $checkout_data['shipping']['rate_id'];

            $shipping_step = $this->getStep('shipping');
            /**
             * @var shopCheckoutShipping $shipping_step
             */
            //TODO use shopCheckout::verify()
            $rate = $shipping_step->getRate($order['params']['shipping_id'], $order['params']['shipping_rate_id']);

            $order['params']['shipping_plugin'] = $rate['plugin'];
            $order['params']['shipping_name'] = $rate['name'];
            $order['params']['shipping_tax_id'] = $rate['tax_id'];
            if (isset($rate['est_delivery'])) {
                $order['params']['shipping_est_delivery'] = $rate['est_delivery'];
            }
            if (!isset($order['shipping'])) {
                $order['shipping'] = $rate['rate'];
            }
            if (!empty($order['params']['shipping'])) {
                foreach ($order['params']['shipping'] as $k => $v) {
                    if (strpos($k, '_') !== 0) {
                        # save params without leading '_'
                        $order['params']['shipping_params_'.$k] = $v;
                    }
                }
                unset($order['params']['shipping']);
            }
        } else {
            $order['shipping'] = 0;
        }
    }
}
