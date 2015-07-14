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
        if ($plugin_id = max(0, intval($id))) {
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
        $model = new shopPluginModel();
        return $model->getPlugin($id, shopPluginModel::TYPE_PAYMENT);
    }

    public static function savePlugin($plugin)
    {
        $default = array(
            'status' => 0,
        );
        $plugin = array_merge($default, $plugin);
        $model = new shopPluginModel();
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
     *
     * formalize order data
     * @param string|array $order order ID or order data
     * @param waPayment|string|string[] $payment_plugin
     * @return waOrder
     * @throws waException
     *
     * @todo: $payment_plugin param
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
        $convert = false;
        if ($payment_plugin && is_object($payment_plugin) && (method_exists($payment_plugin, 'allowedCurrency'))) {
            $allowed_currencies = $payment_plugin->allowedCurrency();
            $total = $order['total'];
            $currency_id = $order['currency'];
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

                    $convert = true;
                    $total = shop_currency($total, $order['currency'], $currency_id = reset($matched_currency), false);
                }
            }
        } elseif (is_array($payment_plugin) || is_string($payment_plugin)) {
            $total = $order['total'];
            $currency_id = $order['currency'];

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
                $convert = true;
                $total = shop_currency($total, $order['currency'], $currency_id = reset($matched_currency), false);
            }
        } else {
            $currency_id = $order['currency'];
            $total = $order['total'];
        }
        $items = array();
        if (!empty($order['items'])) {
            foreach ($order['items'] as $item) {

                ifempty($item['price'], 0.0);
                if ($convert) {
                    $item['price'] = shop_currency($item['price'], $order['currency'], $currency_id, false);
                }
                $items[] = array(
                    'id'          => ifset($item['id']),
                    'name'        => ifset($item['name']),
                    'sku'         => ifset($item['sku_code']),
                    'description' => '',
                    'price'       => $item['price'],
                    'quantity'    => ifset($item['quantity'], 0),
                    'total'       => $item['price'] * $item['quantity'],
                    'type'        => ifset($item['type'], 'product'),
                    'product_id'  => ifset($item['product_id']),
                );
                if (isset($item['weight'])) {
                    $items[count($items) - 1]['weight'] = $item['weight'];
                }
            }
        }

        $empty_address = array(
            'firstname' => '',
            'lastname'  => '',
            'country'   => '',
            'region'    => '',
            'city'      => '',
            'street'    => '',
            'zip'       => '',
        );

        $shipping_address = array_merge($empty_address, shopHelper::getOrderAddress($order['params'], 'shipping'));
        $billing_address = array_merge($empty_address, shopHelper::getOrderAddress($order['params'], 'billing'));
        if (!count(array_filter($billing_address, 'strlen'))) {
            $billing_address = $shipping_address;
        }

        ifset($order['shipping'], 0.0);
        ifset($order['discount'], 0.0);
        ifset($order['tax'], 0.0);
        if ($convert) {
            $order['tax'] = shop_currency($order['tax'], $order['currency'], $currency_id, false);
            $order['shipping'] = shop_currency($order['shipping'], $order['currency'], $currency_id, false);
            $order['discount'] = shop_currency($order['discount'], $order['currency'], $currency_id, false);
        }
        $order_data = array(
            'id_str'           => ifempty($order['id_str'], $order['id']),
            'id'               => $order['id'],
            'contact_id'       => $order['contact_id'],
            'datetime'         => ifempty($order['create_datetime']),
            'description'      => sprintf(_w('Payment for order %s'), ifempty($order['id_str'], $order['id'])),
            'update_datetime'  => ifempty($order['update_datetime']),
            'paid_datetime'    => empty($order['paid_date']) ? null : ($order['paid_date'].' 00:00:00'),
            'total'            => ifempty($total, $order['total']),
            'currency'         => ifempty($currency_id, $order['currency']),
            'discount'         => $order['discount'],
            'tax'              => $order['tax'],
            'payment_name'     => ifset($order['params']['payment_name'], ''),
            'billing_address'  => $billing_address,
            'shipping'         => $order['shipping'],
            'shipping_name'    => ifset($order['params']['shipping_name'], ''),
            'shipping_address' => $shipping_address,
            'items'            => $items,
            'comment'          => ifempty($order['comment'], ''),
            'params'           => $order['params'],
        );
        return waOrder::factory($order_data);
    }

    public function getDataPath($order_id, $path = null)
    {
        $str = str_pad($order_id, 4, '0', STR_PAD_LEFT);
        $path = 'orders/'.substr($str, -2).'/'.substr($str, -4, 2).'/'.$order_id.'/payment/'.$path;
        return wa('shop')->getDataPath($path, false, 'shop');
    }

    public function getSettings($payment_id, $merchant_key)
    {
        $this->merchant_id = max(0, intval($merchant_key));
        if (wa()->getEnv() == 'frontend') {
            if ($info = self::getPluginData($this->merchant_id)) {
                if ($payment_id != ifset($info['plugin'])) {
                    throw new waException ('Invalid merchant id', 404);
                }
                if (!$info['status']) {
                    throw new waException('Plugin status is disabled', 503);
                }
            } else {
                throw new waException('Plugin not found', 404);
            }
        }
        return $this->model()->get($merchant_key);
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
        //todo
        return;
        if (empty($params['transaction']['plugin'])) {
            throw new ordersJsonException('Empty payment system ID');
        }
        $params['order']->getMerchant();
        if (empty($params['order']['merchant_info'])) {
            throw new ordersJsonException('Empty merechant info');
        }
        $module = waPayment::factory($params['transaction']['plugin'], $params['order']['merchant_info']['id'], self::getInstance());

        $result = $module->refund(array(
            'transaction'   => $params['transaction'],
            'refund_amount' => $params['refund_amount']
        ));
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
        //TODO
        $success_back_url = wa()->getRouteUrl('shop/checkout/success', true);
    }

    public function getBackUrl($type = self::URL_SUCCESS, $transaction_data = array())
    {
        if (!empty($transaction_data['order_id'])) {
            //TODO set routing params for request (domain & etc)
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
                $url = wa()->getRouteUrl('shop/frontend', true);
                break;
        }
        return $url;
    }


    protected function workflowAction($method, $transaction_data)
    {
        $order_model = new shopOrderModel();
        $order = $order_model->getById($transaction_data['order_id']);
        if (!$order) {
            return array('error' => 'Order not found');
        }
        $appropriate = $this->isSuitable($order['id']);
        if ($appropriate !== true) {
            return array('error' => $appropriate);
        }

        $result = array();
        if (empty($transaction_data['customer_id'])) {
            $result['customer_id'] = $order['contact_id'];
        }
        $workflow = new shopWorkflow();
        $workflow->getActionById($method)->run($transaction_data);

        return $result;
    }

    /**
     * Verify that the plugin is suitable for payment of the order
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
                return 'Plugin does not suitable to payment of the order';
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
        $result = $this->workflowAction('callback', $transaction_data);
        if (empty($result['error'])) {
            $workflow = new shopWorkflow();
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
        return $this->workflowAction('callback', $transaction_data);
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackDeclineHandler($transaction_data)
    {
        return $this->workflowAction('callback', $transaction_data);
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackRefundHandler($transaction_data)
    {
        $result = $this->workflowAction('callback', $transaction_data);
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
        return $this->workflowAction('callback', $transaction_data);
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackConfirmationHandler($transaction_data)
    {
        $result = $this->workflowAction('callback', $transaction_data);
        if (empty($result['error'])) {
            $order_model = new shopOrderModel();
            $order = $order_model->getById($transaction_data['order_id']);
            $result['result'] = true;
            $total = $transaction_data['amount'];

            if ($transaction_data['currency_id'] != $order['currency']) {
                $order_total = shop_currency($order['total'], $order['currency'], $transaction_data['currency_id'], false);
            } else {
                $order_total = $order['total'];
            }
            if (abs($order_total - $total) > 0.01) {
                $result['result'] = false;
                $result['error'] = sprintf('Invalid order amount: expect %f, but get %f in %s', $order_total, $total, $transaction_data['currency_id']);
            } else {
                $workflow = new shopWorkflow();
                $workflow->getActionById('process')->run($transaction_data['order_id']);
            }
        }
        return $result;
    }

    /**
     * @param array $transaction_data
     * @return array
     */
    public function callbackNotifyHandler($transaction_data)
    {
        return $this->workflowAction('callback', $transaction_data);
    }
}
