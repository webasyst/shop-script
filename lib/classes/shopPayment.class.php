<?php

class shopPayment extends waAppPayment
{
    const DUMMY = 'dummy';
    private static $instance;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function init()
    {
        $this->app_id = 'shop';
        parent::init();
    }

    /**
     *
     * @return shopPluginSettingsModel
     */
    private function model()
    {
        static $model;
        if (!$model) {
            $model = new shopPluginSettingsModel();
        }
        return $model;
    }

    /**
     * @return shopPluginModel
     */
    private static function pluginModel()
    {
        static $model;
        if (!$model) {
            $model = new shopPluginModel();
        }
        return $model;
    }

    /**
     *
     * @param string $plugin plugin identity string (e.g. PayPal/WebMoney)
     * @param int $plugin_id plugin instance id
     * @throws waException
     * @return waPayment
     */
    public static function getPlugin($plugin, $plugin_id = null)
    {
        if (!$plugin && $plugin_id) {
            $info = self::getPluginData($plugin_id);

            if (!$info) {
                throw new waException("Payment plugin {$plugin_id} not found", 404);
            }

            $plugin = $info['plugin'];
        }
        if ($plugin == self::DUMMY) {
            return shopPaymentDummy::getDummy();
        } else {
            return waPayment::factory($plugin, $plugin_id, self::getInstance());
        }
    }

    public static function getPluginInfo($id)
    {
        $plugin_id = max(0, intval($id));
        if ($plugin_id) {
            $info = self::getPluginData($plugin_id);
            if (!$info) {
                throw new waException("Payment plugin {$plugin_id} not found", 404);
            }
        } else {
            $info = array(
                'plugin' => $id,
                'status' => 1,
            );
        }

        if ($info['plugin'] == self::DUMMY) {
            $default_info = shopPaymentDummy::dummyInfo();
        } else {
            $default_info = waPayment::info($info['plugin']);
        }
        return is_array($default_info) ? array_merge($default_info, $info) : $default_info;
    }

    public static function getList()
    {
        if (!class_exists('waPayment')) {
            throw new waException(_w('Payment plugins not installed yet'));
        }
        $list = waPayment::enumerate();
        $list['dummy'] = shopPaymentDummy::dummyInfo();
        return $list;
    }

    private static function getPluginData($id)
    {
        return self::pluginModel()->getPlugin($id, shopPluginModel::TYPE_PAYMENT);
    }

    public static function savePlugin($plugin)
    {
        $default = array(
            'status' => 0,
        );
        $plugin = array_merge($default, $plugin);
        $model = self::pluginModel();
        if (!empty($plugin['id']) && ($id = max(0, intval($plugin['id']))) && ($row = $model->getByField(array('id' => $id, 'type' => shopPluginModel::TYPE_PAYMENT)))) {
            $plugin['plugin'] = $row['plugin'];
            $model->updateById($plugin['id'], $plugin);
        } elseif (!empty($plugin['plugin'])) {
            $plugin['type'] = shopPluginModel::TYPE_PAYMENT;
            $plugin['id'] = $model->insert($plugin);
        }
        if (!empty($plugin['id']) && isset($plugin['settings']) && ($plugin['plugin'] != self::DUMMY)) {
            waPayment::factory($plugin['plugin'], $plugin['id'], self::getInstance())->saveSettings($plugin['settings']);
        }
        if (!empty($plugin['id'])) {
            ifset($plugin['shipping'], array());
            $plugins = $model->listPlugins(shopPluginModel::TYPE_SHIPPING, array('all' => true,));
            $app_settings = new waAppSettingsModel();
            $settings = json_decode($app_settings->get('shop', 'shipping_payment_disabled', '{}'), true);
            if (empty($settings) || !is_array($settings)) {
                $settings = array();
            }
            if (!isset($settings[$plugin['id']])) {
                $settings[$plugin['id']] = array();
            }
            $s =& $settings[$plugin['id']];
            foreach ($plugins as $item) {
                if (empty($plugin['shipping'][$item['id']])) {
                    $s[] = $item['id'];
                } else {
                    $key = array_search($item['id'], $s);
                    if ($key !== false) {
                        unset($s[$key]);
                    }
                }
            }

            $s = array_unique($s);

            if (empty($s)) {
                unset($settings[$plugin['id']]);
            }
            $app_settings->set('shop', 'shipping_payment_disabled', json_encode($settings));
        }
        return $plugin;
    }

    public function setOrderParams($order_id, $params)
    {
        $params_model = new shopOrderParamsModel();
        $params_model->set($order_id, $params, false);
    }

    /**
     * @param $transaction_data
     * @param string $comment
     * @return int
     */
    private function createOrder($transaction_data, $comment = null)
    {
        $contact = $this->createContact();

        $order = array(
            'contact'   => $contact,
            'items'     => array(),
            'shipping'  => 0,
            'discount'  => false,//not apply discounts
            'currency'  => ifset($transaction_data['currency_id']),
            'unsettled' => 1,
            'params'    => array(),

        );

        $order['items'][] = array(
            'name'       => 'unknown_name',
            'currency'   => ifset($transaction_data['currency_id']),
            'type'       => 'dummy',//type dummy will be ignored by default it's 'product' or 'service'
            'sku_id'     => null,
            'product_id' => null,
            'price'      => $transaction_data['amount'],
            'quantity'   => 1,
        );

        $plugin_info = self::pluginModel()->getById($this->merchant_id);
        $order['params']['payment_id'] = $this->merchant_id;
        $order['params']['payment_plugin'] = $transaction_data['plugin'];
        $order['params']['payment_name'] = $plugin_info['name'];
        $order['params']['payment_description'] = $plugin_info['description'];

        //TODO setup offline item
        //$order['params']['shipping_name'] = 'Offline store';
        //$order['params']['storefront'] = wa()->getConfig()->getDomain();
        //TODO add stock_id
        //$order['params']['stock_id'] = 100500;

        $order['params']['ip'] = waRequest::getIp();
        $order['params']['user_agent'] = waRequest::getUserAgent();

        if (!$order['params']['user_agent']) {
            $order['params']['user_agent'] = 'payment';
        }
        if ($contact) {
            foreach (array('shipping', 'billing') as $ext) {
                $address = $contact->getFirst('address.'.$ext);
                if ($address) {
                    foreach ($address['data'] as $k => $v) {
                        $order['params'][$ext.'_address.'.$k] = $v;
                    }
                }
            }
        }

        if (!empty($comment)) {
            $order['comment'] = $comment;
        }
        $workflow = new shopWorkflow();
        return $workflow->getActionById('create')->run($order);
    }

    private function createContact()
    {
        $contact = new waContact();
        $contact['firstname'] = '(nobody)';
        $contact['create_app_id'] = 'shop';

        return $contact;
    }

    /**
     *
     * formalize order data
     * @param string|array $order order ID or order data
     * @param waPayment|string|string[] $payment_plugin
     * @return waOrder
     * @throws waException
     */
    public static function getOrderData($order, $payment_plugin = null)
    {
        if (!is_array($order)) {
            $order_id = shopHelper::decodeOrderId($encoded_order_id = $order);
            if (!$order_id) {
                $order_id = $encoded_order_id;
                $encoded_order_id = shopHelper::encodeOrderId($order_id);
            }

            $om = new shopOrderModel();
            $order = $om->getOrder($order_id);
            if (!$order) {
                return null;
            }
            $order['id_str'] = $encoded_order_id;
        }

        if (!isset($order['id_str'])) {
            $order['id_str'] = shopHelper::encodeOrderId($order['id']);
        }

        if (!isset($order['params'])) {

            $order_params_model = new shopOrderParamsModel();
            $order['params'] = $order_params_model->get($order['id']);
        }

        $options = array();

        if ($payment_plugin && is_object($payment_plugin) && (method_exists($payment_plugin, 'allowedCurrency'))) {
            $allowed_currencies = $payment_plugin->allowedCurrency();
            $options['total'] = $order['total'];
            $options['currency'] = $order['currency'];
            if ($allowed_currencies !== true) {
                $allowed_currencies = (array)$allowed_currencies;


                if (!in_array($order['currency'], $allowed_currencies)) {
                    $config = wa('shop')->getConfig();
                    /**
                     * @var shopConfig $config
                     */
                    $currencies = $config->getCurrencies();
                    $matched_currency = array_intersect($allowed_currencies, array_keys($currencies));
                    if (!$matched_currency) {
                        if ($payment_plugin instanceof waPayment) {
                            $message = _w('Payment procedure cannot be processed because required currency %s is not defined in your store settings.');
                        } else {
                            $message = _w('Data cannot be processed because required currency %s is not defined in your store settings.');
                        }
                        throw new waException(sprintf($message, implode(', ', $allowed_currencies)));
                    }
                    $options['currency'] = reset($matched_currency);
                    $options['total'] = shop_currency($options['total'], $order['currency'], $options['currency'], false);
                }
            }
        } elseif (is_array($payment_plugin) || is_string($payment_plugin)) {
            $options['total'] = $order['total'];
            $options['currency'] = $order['currency'];

            $allowed_currencies = (array)$payment_plugin;
            if (!in_array($order['currency'], $allowed_currencies)) {
                $config = wa('shop')->getConfig();
                /**
                 * @var shopConfig $config
                 */
                $currencies = $config->getCurrencies();
                $matched_currency = array_intersect($allowed_currencies, array_keys($currencies));
                if (!$matched_currency) {
                    $message = _w('Data cannot be processed because required currency %s is not defined in your store settings.');
                    throw new waException(sprintf($message, implode(', ', $allowed_currencies)));
                }
                $options['currency'] = reset($matched_currency);
                $options['total'] = shop_currency($options['total'], $order['currency'], $options['currency'], false);
            }
        } else {
            $options['currency'] = $order['currency'];
            $options['total'] = $order['total'];
        }

        return shopHelper::getWaOrder($order, $options);
    }

    public function getDataPath($order_id, $path = null)
    {
        $str = str_pad($order_id, 4, '0', STR_PAD_LEFT);
        $path = 'orders/'.substr($str, -2).'/'.substr($str, -4, 2).'/'.$order_id.'/payment/'.$path;
        return wa('shop')->getDataPath($path, false, 'shop', false);
    }

    public function getSettings($payment_id, $merchant_key)
    {
        $this->merchant_id = max(0, is_array($merchant_key) || is_callable($merchant_key) ? null : intval($merchant_key));
        if (wa()->getEnv() == 'frontend') {
            if (!$this->merchant_id && (($merchant_key === '*') || (is_callable($merchant_key)))) {
                //magic case for suggest merchant_id
                $suggest = array(
                    'plugin' => $payment_id,
                    'type'   => shopPluginModel::TYPE_PAYMENT,
                    'status' => 1,
                );

                $count = self::pluginModel()->countByField($suggest);

                if ($count == 1) {
                    $info = self::pluginModel()->getByField($suggest);
                    $this->merchant_id = (int)$info['id'];
                } elseif ($count && is_callable($merchant_key)) {
                    /**
                     * @var callable $merchant_key
                     */
                    $matched = self::pluginModel()->getByField($suggest, true);
                    $info = null;
                    foreach ($matched as $_info) {
                        $settings = $this->model()->get($_info['id']);
                        if ($settings && call_user_func($merchant_key, $settings)) {
                            if ($info) {
                                throw new waException('Empty merchant id', 404);
                            }
                            $info = $_info;
                        }
                    }
                    if (!$info) {
                        throw new waException('Empty merchant id', 404);
                    }
                    $this->merchant_id = (int)$info['id'];
                } else {
                    throw new waException('Empty merchant id', 404);
                }
            } else {
                $info = self::getPluginData($this->merchant_id);
            }
            if ($info) {
                if ($payment_id != ifset($info['plugin'])) {
                    throw new waException('Invalid merchant id', 404);
                }
                if (!$info['status']) {
                    throw new waException('Plugin status is disabled', 503);
                }
            } else {
                throw new waException('Plugin not found', 404);
            }
        }
        return $this->model()->get($this->merchant_id);
    }

    public function setSettings($plugin_id, $key, $name, $value)
    {
        $this->model()->set($key, $name, $value);
    }

    public function cancel()
    {

    }

    public function refund()
    {
        $result = null;
        if (false) {
            if (empty($params['payment_id'])) {
                throw new waException('Empty payment system ID');
            }

            $module = waPayment::factory(null, $params['payment_id'], self::getInstance());

            if ($module instanceof waIPaymentRefund) {
                //todo
                $result = $module->refund(array(
                    'transaction'   => $params['transaction'],
                    'refund_amount' => $params['refund_amount']
                ));
            }
        }
        return $result;
    }

    public function auth()
    {

    }

    public function capture()
    {

    }

    public function payment()
    {

    }

    public function void()
    {

    }

    public function paymentForm()
    {
        $success_back_url = wa()->getRouteUrl('shop/checkout/success', true);
        return compact('success_back_url');
    }

    public function getBackUrl($type = self::URL_SUCCESS, $transaction_data = array())
    {
        if (!empty($transaction_data['order_id'])) {
            # set routing params for request (domain & etc)
            $model = new shopOrderParamsModel();
            $order_domain = $model->getOne($transaction_data['order_id'], 'storefront');
            $params = array(
                'code' => waRequest::param('code'),
            );
            if (empty($params['code'])) {
                unset($params['code']);
            }
            if ($order_domain) {
                $order_domain = preg_replace('@^(https?://)?(www\.)?@', '', rtrim($order_domain, '/'));
                $routing = wa()->getRouting();
                $domain_routes = $routing->getByApp('shop');
                foreach ($domain_routes as $domain => $routes) {
                    foreach ($routes as $route) {
                        $settlement = $domain.'/'.$route['url'];
                        $settlement = preg_replace('@^(https?://)?(www\.)?@', '', rtrim($settlement, '/*'));
                        if ($settlement == $order_domain) {
                            $routing->setRoute($route, $domain);
                            waRequest::setParam($route + $params);
                            break 2;
                        }
                    }
                }
            }
        }
        switch ($type) {
            case self::URL_PRINTFORM:
                ifempty($transaction_data['printform'], 0);
                $params = array(
                    'id'        => $transaction_data['order_id'],
                    'code'      => waRequest::param('code'),
                    'form_type' => 'payment',
                    'form_id'   => ifempty($transaction_data['printform'], 'payment'),
                );

                if (empty($params['code'])) {
                    unset($params['code']);
                }
                $action = 'shop/frontend/myOrderPrintform';

                $url = wa()->getRouteUrl($action, $params, true);
                break;

            case self::URL_SUCCESS:
                $url = wa()->getRouteUrl('shop/frontend/checkout', array('step' => 'success'), true);
                if (!empty($transaction_data['order_id'])) {
                    $url .= '?order_id='.$transaction_data['order_id'];
                } elseif ($order_id = wa()->getStorage()->get('shop/order_id')) {
                    $url .= '?order_id='.$order_id;
                }
                break;
            case self::URL_FAIL:
            case self::URL_DECLINE:
                $url = wa()->getRouteUrl('shop/frontend/checkout', array('step' => 'error'), true);
                if (!empty($transaction_data['order_id'])) {
                    $url .= '?order_id='.$transaction_data['order_id'];
                }
                break;
            default:
                $url = wa()->getRouteUrl('shop/frontend', array(), true);
                break;
        }
        return $url;
    }

    protected function callbackAction($transaction_data, $check_amount = false)
    {
        $result = array();

        if (!$this->merchant_id) {
            $result['error'] = 'Invalid plugin id';
        } else {

            $unsettled_order_id = null;
            $order_model = new shopOrderModel();

            $workflow = new shopWorkflow();
            $callback = $workflow->getActionById('callback');
            /**
             * @var shopWorkflowCallbackAction $callback
             */

            if (empty($transaction_data['order_id']) || ($transaction_data['order_id'] === 'offline')) {
                $plugin = ifset($transaction_data['payment_plugin_instance']);
                /**
                 * @var waPayment $plugin
                 */
                if (!empty($transaction_data['unsettled'])
                    && $plugin
                    && ($plugin instanceof waPayment) && $plugin->getProperties('offline')
                ) {
                    #create unsettled order for mobile terminals callback
                    $unsettled_order_id = $this->createOrder($transaction_data);
                } else {
                    $result['error'] = 'Order not found';
                }
            } else {
                $order = $order_model->getById($transaction_data['order_id']);
                if (!$order) {
                    $result['error'] = 'Order not found';
                } else {
                    $appropriate = $this->isSuitable($order['id']);
                    if ($appropriate !== true) {
                        $result['error'] = $appropriate;
                    } elseif ($check_amount) {
                        $result['error'] = null;
                        $this->isOrderAmountInvalid($transaction_data, $result['error']);
                    }
                }


                if (!empty($result['error'])) {
                    $transaction_data['callback_declined'] = $result['error'];

                    if (!empty($transaction_data['unsettled'])) {
                        $plugin = ifset($transaction_data['payment_plugin_instance']);
                        /**
                         * @var waPayment $plugin
                         */
                        if ($plugin && ($plugin instanceof waPayment) && $plugin->getProperties('offline')) {
                            #create unsettled order for mobile terminals callback
                            $unsettled_order_id = $this->createOrder($transaction_data);
                        }
                    }

                    if ($order) {
                        $log_transaction_data = $transaction_data;
                        $log_transaction_data['unsettled_order_id'] = $unsettled_order_id;
                        $callback->run($log_transaction_data);
                    }
                }
            }

            if (!empty($unsettled_order_id)) {
                if (!empty($result['error'])) {
                    if (!empty($order)) {
                        $transaction_data['original_order_id'] = $order['id'];
                    }
                    unset($result['error']);
                }
                $transaction_data['order_id'] = $unsettled_order_id;
                $result['order_id'] = $unsettled_order_id;
            }

            if (empty($transaction_data['customer_id']) && !empty($order['contact_id'])) {
                $result['customer_id'] = $order['contact_id'];
                $transaction_data['customer_id'] = $order['contact_id'];
            }
            if (empty($result['error'])) {
                $callback->run($transaction_data);
            }
        }

        return $result;
    }

    /**
     * Verify if payment type is valid for this order
     * @param int $order_id
     * @return bool|string
     */
    private function isSuitable($order_id)
    {
        if (!$this->merchant_id) {
            return 'Invalid plugin id';
        } else {
            $order_params_model = new shopOrderParamsModel();

            if ($this->merchant_id != $order_params_model->getOne($order_id, 'payment_id')) {
                return 'Order payment type did not match the callback request';
            }
        }
        return true;
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackPaymentHandler($transaction_data)
    {
        $result = $this->callbackAction($transaction_data, true);
        if (empty($result['error'])) {
            $workflow = new shopWorkflow();
            if (!empty($result['order_id'])) {
                $transaction_data['order_id'] = $result['order_id'];
            }

            $workflow->getActionById('pay')->run($transaction_data['order_id']);
            $result['result'] = true;
        }
        return $result;
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackCancelHandler($transaction_data)
    {
        return $this->callbackAction($transaction_data);
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackDeclineHandler($transaction_data)
    {
        return $this->callbackAction($transaction_data);
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackRefundHandler($transaction_data)
    {
        $result = $this->callbackAction($transaction_data);
        if (empty($result['error'])) {
            $workflow = new shopWorkflow();
            $workflow->getActionById('refund')->run($transaction_data['order_id']);
        }
        return $result;
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackCaptureHandler($transaction_data)
    {
        return $this->callbackPaymentHandler($transaction_data);
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackChargebackHandler($transaction_data)
    {
        return $this->callbackAction($transaction_data);
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackConfirmationHandler($transaction_data)
    {
        $result = $this->callbackAction($transaction_data);
        if (empty($result['error'])) {
            if (!empty($result['order_id'])) {
                $transaction_data['order_id'] = $result['order_id'];
            }

            $result['result'] = true;

            $error = null;
            if ($this->isOrderAmountInvalid($transaction_data, $error)) {
                $result['result'] = false;
                $result['error'] = $error;
            } else {
                $workflow = new shopWorkflow();
                $workflow->getActionById('process')->run($transaction_data['order_id']);
            }
        }
        return $result;
    }

    private function isOrderAmountInvalid($transaction_data, &$error)
    {
        $order_model = new shopOrderModel();
        $order = $order_model->getById($transaction_data['order_id']);
        $result['result'] = true;


        if (isset($transaction_data['amount'])) {
            $total = floatval(str_replace(',', '.', $transaction_data['amount']));

            if ($transaction_data['currency_id'] != $order['currency']) {
                $order_total = shop_currency($order['total'], $order['currency'], $transaction_data['currency_id'], false);
            } else {
                $order_total = $order['total'];
            }


            $invalid = !empty($order_total) && (abs($order_total - $total) > 0.01);
            if ($invalid) {
                $error = sprintf('Invalid order amount: %0.2f expected, %0.2f received (%s)', $order_total, $total, $transaction_data['currency_id']);
            }
        } else {
            $invalid = false;
        }

        return $invalid;
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackNotifyHandler($transaction_data)
    {
        return $this->callbackAction($transaction_data);
    }
}
