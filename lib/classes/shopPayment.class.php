<?php
class shopPayment extends waAppPayment
{
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
     * @return waPayment
     */
    public static function getPlugin($plugin, $plugin_id = null)
    {
        if (!$plugin && $plugin_id) {
            $model = new shopPluginModel();
            $info = $model->getById($plugin_id);

            if (!$info) {
                throw new waException("Payment plugin {$plugin_id} not found", 404);
            }

            if ($info['type'] != shopPluginModel::TYPE_PAYMENT) {
                throw new waException("Payment plugin {$plugin_id} has invalid type", 404);
            }
            $plugin = $info['plugin'];
        }
        return waPayment::factory($plugin, $plugin_id, self::getInstance());
    }

    public static function getPluginInfo($id)
    {
        if ($plugin_id = max(0, intval($id))) {

            $model = new shopPluginModel();
            $info = $model->getById($plugin_id);

            if (!$info) {
                throw new waException("Payment plugin {$plugin_id} not found", 404);
            }
        } else {
            $info = array(
                'plugin' => $id,
                'status' => 1,
            );
        }

        $default_info = waPayment::info($info['plugin']);
        return array_merge($default_info, $info);
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
        if (!empty($plugin['id']) && isset($plugin['settings'])) {
            waPayment::factory($plugin['plugin'], $plugin['id'], self::getInstance())->saveSettings($plugin['settings']);
        }
        if (!empty($plugin['id'])) {
            $shipping = ifset($plugin['shipping'], array());
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
     * @param waPayment $payment_plugin
     * @return waOrder
     */
    public static function getOrderData($order = array(), $payment_plugin = null)
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
        if ($payment_plugin && (method_exists($payment_plugin, 'allowedCurrency'))) {
            $currency = $payment_plugin->allowedCurrency();
            $total = $order['total'];
            $currency_id = $order['currency'];
            if ($currency !== true) {
                $currency = (array)$currency;
                if (!in_array($order['currency'], $currency)) {
                    $convert = true;
                    $total = shop_currency($total, $order['currency'], $currency_id = reset($currency), false);

                }
            }
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
                    'sku'        => ifset($item['sku_code']),
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
        $wa_order = waOrder::factory(array(
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
        ));
        return $wa_order;
    }

    public function getDataPath($order_id, $path = null)
    {
        $str = str_pad($order_id, 4, '0', STR_PAD_LEFT);
        $path = 'orders/'.substr($str, -2).'/'.substr($str, -4, 2).'/'.$order_id.'/payment/'.$path;
        return wa('shop')->getDataPath($path, false, 'shop');
    }

    public function getSettings($payment_id, $merchant_key)
    {
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
        $result = array();
        if (empty($transaction_data['customer_id'])) {
            $result['customer_id'] = $order['contact_id'];
        }
        $workflow = new shopWorkflow();
        $workflow->getActionById($method)->run($transaction_data);

        return $result;
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
                $total = shop_currency($total, $transaction_data['currency_id'], $order['currency'], false);
            }
            if (abs($order['total'] - $total) > 0.01) {
                $result['result'] = false;
                $result['error'] = sprintf('Invalid order amount: expect %f, but get %f', $order['total'], $total);
            } else {
                $workflow = new shopWorkflow();
                $workflow->getActionById('process')->run($transaction_data['order_id']);
            }
        }
        return $result;
    }
}
