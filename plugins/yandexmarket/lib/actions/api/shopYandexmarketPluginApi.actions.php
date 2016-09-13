<?php

class shopYandexmarketPluginApiActions extends waActions
{
    private $format = 'application/json';


    private $profile;

    protected function preExecute()
    {
        //TODO setup number formats into dot
        parent::preExecute();
    }

    public function cartAction()
    {
        /**
         * @link https://tech.yandex.ru/market/partner/doc/dg/reference/post-cart-docpage/
         */

        $order = $this->getApiRequest();

        $items = array();

        $total = 0;

        foreach ($order->items as $item) {
            $items[] = array(
                'feedId'   => $item['raw_data']['feedId'],
                'offerId'  => $item['raw_data']['offerId'],
                'price'    => floatval($item['price']),
                'count'    => (int)$item['count'],
                'delivery' => true,
            );

            $total += floatval($item['price']) * (int)$item['count'];
        }

        $carriers = array();
        $payments = array();

        shopShipping::getList();

        if (count($items)) {
            $this->getProfile($order->items);

            $plugin_model = new shopPluginModel();

            $internal_added = false;

            $days = array(7);
            if (ifset($this->profile['config']['shop']['local_delivery_estimate'])) {
                $days = array_map('intval', preg_split('@\D+@', $this->profile['config']['shop']['local_delivery_estimate'], 2));
            }

            $working_days = array();
            $offset = 0;

            $now = time();
            $time = (int)date('G', $now);
            $week_day = (int)date('N', $now) - 1;
            $timezone = ifset($this->profile['config']['shop']['timezone']);
            if ($timezone) {
                $time = (int)waDateTime::date('G', $now, $timezone);
                $week_day = (int)waDateTime::date('N', $now, $timezone) - 1;
            }

            if (ifset($this->profile['config']['shop']['local_delivery_order_before_mode']) == 'per-day') {
                for ($day = 0; $day < 7; $day++) {
                    $day_info = ifset($this->profile['config']['shop']['local_delivery_order_before_per_day'][$day], array());
                    if (!empty($day_info['workday'])) {
                        $working_days[$day] = ifset($day_info['before'], 24);
                    }
                }
                if ($working_days) {
                    while ($time >= ifset($working_days[($week_day + $offset) % 7])) {
                        ++$offset;
                        $time = null;
                    }
                    $working_days = array_filter($working_days);
                }

            } else {
                $order_before = ifset($this->profile['config']['shop']['local_delivery_order_before'], 24);
                if ($time >= $order_before) {
                    $offset = 1;//next day
                }
            }

            if (count($days) == 2) {
                $to_days = end($days) + $offset;
                $from_days = reset($days) + $offset;
            } else {
                $to_days = reset($days) + $offset;
                $from_days = $offset;
            }

            if (!empty($working_days) && (count($working_days) < 7)) {
                for ($day = $from_days; $day <= $to_days; $day++) {
                    if (empty($working_days[($day + $week_day) % 7])) {
                        ++$to_days;
                    }
                }
            }

            $to_date_timestamp = strtotime(sprintf('+%ddays', $to_days));
            $from_date_timestamp = $from_days ? strtotime(sprintf('+%ddays', $from_days)) : time();

            if (ifset($this->profile['config']['shop']['delivery'], '') === 'true') {
                if (ifset($this->profile['config']['shop']['deliveryIncluded']) === 'true') {
                    $price = 0;
                } else {
                    $price = intval(ifset($this->profile['config']['shop']['local_delivery_cost']));
                }

                if (true) {
                    $internal_added = true;
                    $carriers[] = array(
                        'id'          => 'courier',
                        'type'        => 'DELIVERY',
                        'serviceName' => 'Курьер',
                        'price'       => $price,
                        'dates'       => array(
                            'fromDate' => date('d-m-Y', $from_date_timestamp),
                            'toDate'   => date('d-m-Y', $to_date_timestamp),
                        ),
                    );
                }

                if (false) {
                    $methods = $plugin_model->listPlugins('shipping');
                    if (count($methods)) {
                        foreach ($methods as $result) {
                            if (!$result['status']) {
                                continue;
                            }
                            //TODO calculate delivery prices
                            $carriers[] = array(
                                'id'          => sprintf('shipping.%s', $result['id']),
                                'type'        => 'DELIVERY',
                                'serviceName' => $result['name'],
                                'price'       => floatval($price),
                                'dates'       => array(
                                    'fromDate' => date('d-m-Y', $from_date_timestamp),
                                    'toDate'   => date('d-m-Y', $to_date_timestamp),
                                ),
                            );
                        }
                    }
                }
            }


            $outlets_added = false;
            if (ifset($this->profile['config']['shop']['pickup'], '') === 'true') {
                $outlets = null;
                try {
                    $outlets = $this->getPlugin()->getOutlets($order->campaign_id);
                } catch (waException $ex) {
                    $message = $ex->getMessage();
                    waLog::log(sprintf('%d: %s', $order->campaign_id, $message).var_export($items, true), 'shop/plugins/yandexmarket/api.error.log');
                }
                if ($outlets) {
                    # PICKUP delivery options
                    foreach ($outlets as $outlet) {
                        if (true
                            && (ifset($outlet['status']) == 'MODERATED')
                            && (ifset($outlet['visibility']) != 'HIDDEN ')
                            && in_array(ifset($outlet['type']), array('MIXED', 'DEPOT'), true)
                        ) {

                            $price = 0;
                            if (!empty($outlet['deliveryRules'])) {
                                foreach ($outlet['deliveryRules'] as $delivery_rule) {
                                    $match = false;
                                    if (empty($delivery_rule['priceFrom']) || (floatval($delivery_rule['priceFrom']) >= $total)) {
                                        $match = true;
                                    }
                                    if (!empty($delivery_rule['priceTo']) && (floatval($delivery_rule['priceTo']) > $total)) {
                                        $match = false;
                                    }
                                    if ($match) {
                                        $price = floatval($delivery_rule['cost']);
                                    }
                                }
                            }

                            //TODO parse dates
                            $address = '';
                            if (!empty($outlet['address'])) {
                                //TODO check max ServiceName length
                                //$address = implode(', ', $outlet['address']);
                            }
                            //TODO group outlets by delivery service
                            $carriers[] = array(
                                'id'          => sprintf('outlet.%s', $outlet['id']),
                                'serviceName' => mb_substr(trim(sprintf('%s %s', $outlet['name'], $address)), 0, 50),
                                'type'        => 'PICKUP',
                                'price'       => $price,
                                'dates'       => array(
                                    'fromDate' => date('d-m-Y', $from_date_timestamp),
                                    'toDate'   => date('d-m-Y', $to_date_timestamp),
                                ),
                                'outlets'     => array(
                                    array(
                                        'id' => $outlet['id'],
                                    ),
                                ),
                            );
                            $outlets_added = true;
                        }

                    }

                }
            }

            if ($outlets_added || $internal_added) {
                $payments[] = 'CASH_ON_DELIVERY';
            }

            $fields = array(
                'plugin' => 'yandexmoney',
                'type'   => shopPluginModel::TYPE_PAYMENT,
                'status' => 1,
            );

            if (intval($plugin_model->countByField($fields)) > 0) {
                $payments[] = 'YANDEX';// — оплата при оформлении (только для России);

                // временно не используется.
                //'SHOP_PREPAID' — предоплата напрямую магазину (только для Украины).

                if ($outlets_added && false) { #if mPOS enabled
                    //TODO add custom settings for courier or detect it automatically
                    $payments[] = 'CARD_ON_DELIVERY';
                }
            }
        }

        $array = array(
            'cart' => array(
                'items'           => $items,
                'deliveryOptions' => $carriers,
                'paymentMethods'  => $payments,
            ),
        );

        $this->sendApiResponse($array);
    }

    public function orderAcceptAction()
    {
        $raw_order = $this->getApiRequest(true);

        $order = array(
            'contact'  => $raw_order->contact_id,
            'items'    => array(),
            'currency' => $raw_order->currency,
            'params'   => array(),
        );

        foreach ($raw_order['items'] as $item) {
            if ($item['count']) {


                $order['items'][] = array(
                    'name'           => ifset($item['name'], $item['raw_data']['offerName']),
                    'currency'       => $raw_order->currency,
                    'type'           => 'product',
                    'sku_id'         => $item['sku_id'],
                    'product_id'     => $item['product_id'],
                    'price'          => $item['price'],
                    'purchase_price' => $item['purchase_price'],
                    'quantity'       => $item['count'],
                );
            }
        }

        $order['discount'] = shopDiscounts::apply($order);

        # it's yandex.market server data
        $order['params']['ip'] = waRequest::getIp();
        $order['params']['user_agent'] = waRequest::getUserAgent();

        $order['params']['sales_channel'] = 'plugin_yandexmarket:';


        $order['comment'] = $raw_order->description;

        # to do use other sources!
        $routing_url = wa()->getRouting()->getRootUrl();
        $order['params']['storefront'] = wa()->getConfig()->getDomain().($routing_url ? '/'.$routing_url : '');

        if ($raw_order->shipping_id || $raw_order->shipping_name) {
            if ($raw_order->shipping_id) {
                $order['params']['shipping_id'] = $raw_order->shipping_id;
            }
            if ($raw_order->outlet_id) {
                $order['params']['yandexmarket.outlet_id'] = $raw_order->outlet_id;
            }

            if ($raw_order->shipping_plugin) {
                $order['params']['shipping_plugin'] = $raw_order->shipping_plugin;
            }

            $order['params']['shipping_name'] = $raw_order->shipping_name;
            $order['params']['shipping_rate_id'] = 'delivery';
            $order['shipping'] = $raw_order->shipping;
        } else {
            $order['shipping'] = 0;
        }

        if ($raw_order->payment_name) {
            $order['params']['payment_name'] = $raw_order->payment_name;
        }

        $order['params']['shipping_address.country'] = 'rus';
        $order['params']['shipping_address.city'] = $raw_order->shipping_address['city'];
        $order['params']['shipping_address.street'] = $raw_order->shipping_address['street'];
        $order['params']['shipping_address.zip'] = $raw_order->shipping_address['zip'];

        $order['params']['yandexmarket.id'] = $raw_order->yandex_id;
        $order['params']['yandexmarket.campaign_id'] = $raw_order->campaign_id;

        if ($raw_order->id) {
            $order_id = $raw_order->id;
        } else {
            $workflow = new shopWorkflow();
            $workflow_action = $workflow->getActionById('create');
            /**
             * @var shopWorkflowCreateAction $workflow_action
             */
            $order_id = $workflow_action->run($order);
        }

        if ($order_id) {
            $array = array(
                'order' => array(
                    'accepted' => true,
                    'id'       => (string)$order_id,
                )
            );
        } else {
            $array = array(
                'order' => array(
                    'accepted' => false,
                    'reason'   => 'OUT_OF_DATE'
                )
            );
        }
        $this->sendApiResponse($array);
    }

    public function orderStatusAction()
    {
        $raw_order = $this->getApiRequest(true);
        if ($raw_order->id) {
            $action_id = null;
            $params = null;
            $callback_params = null;
            switch ($raw_order->status) {
                case 'CANCELLED':# заказ отменен
                    $callback_params = array(
                        'order_id'    => $raw_order->id,
                        'plugin'      => 'shop:yandexmarket',
                        'currency_id' => $raw_order->currency,
                        'amount'      => $raw_order->total,
                        'view_data'   => sprintf('%s: %s', $raw_order->sub_status, $raw_order->sub_status_description),
                        'state'       => waPayment::STATE_CANCELED,
                    );

                    $params = $raw_order->id;
                    $action_id = 'delete';
                    break;
                case 'RESERVED': # заказ в резерве (ожидается подтверждение от пользователя);
                case 'PROCESSING':# заказ можно выполнять
                case 'UNPAID':# заказ оформлен, но еще не оплачен (если выбрана оплата при оформлении)

                    $callback_params = array(
                        'id'          => $raw_order->yandex_id,
                        'order_id'    => $raw_order->id,
                        'plugin'      => 'shop:yandexmarket',
                        'currency_id' => $raw_order->currency,
                        'amount'      => $raw_order->total,
                        'view_data'   => 'Заказ в обработке',
                        'state'       => 'UNPAID',
                    );

                    if ($raw_order->paid_datetime) {
                        $action_id = 'pay';
                        $callback_params['view_data'] = 'Заказ оплачен';
                        $callback_params['state'] = waPayment::STATE_VERIFIED;
                    } else {
                        if ($raw_order->status == 'RESERVED') {
                            $callback_params['view_data'] = 'Заказ зарезевирован';
                        }
                    }

                    if ($raw_order->contact_id) {
                        if ($this->getPlugin()->getSettings('contact_id') != $raw_order->contact_id) {
                            $ord = new shopOrderModel();
                            $ord->updateById($raw_order->id, array('contact_id' => $raw_order->contact_id));
                        }
                    }

                    $customer = new shopCustomerModel();
                    $customer->updateFromNewOrder($raw_order->contact_id, $raw_order->id);

                    $params = $raw_order->id;

                    break;
                default:
                    //XXX log error — unknown order state
                    break;
            }

            if ($callback_params) {
                $workflow = new shopWorkflow();

                $workflow->getActionById('callback')->run($callback_params);
                if ($action_id) {
                    $workflow->getActionById($action_id)->run($params);
                }
            }
        } else {
            throw new waException(sprintf('Order %s not found', $raw_order->id), 404);
        }
    }

    private function checkAuth()
    {
        /**
         * @see https://tech.yandex.ru/market/partner/doc/dg/concepts/identification-docpage/
         */
        $token = strtolower(waRequest::server('Authorization', waRequest::get('auth-token')));
        if (empty($token) || ($token != strtolower($this->getPlugin()->getSettings('market_token')))) {
            waLog::log('Invalid request with token '.var_export($token, true), 'shop/plugins/yandexmarket/api.request.log');
            throw new waException('Access forbidden', 403);
        }
    }

    private function initCart($contact_id)
    {
        $code = waRequest::cookie('shop_cart');
        if (!$code) {
            $code = md5(uniqid(time(), true));
            wa()->getResponse()->setCookie('shop_cart', $code, time() + 30 * 86400, null, '', false, true);
        } else {
            $model = new shopCartItemsModel();
            $model->deleteByField('code', $code);
            wa()->getStorage()->remove('shop/cart');
        }

        $cart = new shopCart($code);
        return $cart;
    }

    /**
     * @return shopYandexmarketPlugin
     * @throws waException
     */
    private function getPlugin()
    {
        static $plugin;
        if (empty($plugin)) {
            $plugin = wa('shop')->getPlugin('yandexmarket');
        }
        return $plugin;
    }

    /**
     * @param bool $save_contact
     * @return shopYandexmarketPluginOrder
     * @throws Exception
     * @throws waException
     */
    private function getApiRequest($save_contact = false)
    {
        $this->checkAuth();

        if (isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
            $raw = !empty($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : null;
        } else {
            $raw = implode("", file('php://input'));
        }

        $content_type = waRequest::server('content_type', 'application/json;charset=utf-8');
        if (strpos($content_type, ';')) {
            list($this->format, $content_encoding) = explode(';', $content_type, 2);
        }

        switch ($this->format) {
            case 'application/json':
                $json = json_decode($raw, true);

                if (!$json || !is_array($json)) {
                    throw new waException('Invalid data');
                }
                $order = shopYandexmarketPluginOrder::createFromJson($json, $this->getPlugin(), $save_contact);

                break;
            case 'application/xml':
                if (!empty($raw)) {
                    libxml_use_internal_errors(true);
                    libxml_clear_errors();
                    try {
                        $xml = new SimpleXMLElement($raw);
                        $errors = libxml_get_errors();
                        if ($errors) {
                            foreach ($errors as $error) {
                                /**
                                 * @var LibXMLError $error
                                 */
                                $error->code;
                            }
                        }
                    } catch (Exception $ex) {
                        throw $ex;
                    }
                } else {
                    throw new waException('Empty POST');
                }
                $order = shopYandexmarketPluginOrder::createFromXml($xml, $this->getPlugin());
                break;
            default:
                $order = new shopYandexmarketPluginOrder();
                break;
        }

        if (!empty($json)) {
            unset($raw);
        } else {
            $server = waRequest::server();
        }
        waLog::log(var_export(compact('raw', 'server', 'headers', 'json'), true), 'shop/plugins/yandexmarket/api.request.log');
        return $order;
    }

    protected function sendApiResponse($data)
    {

        switch ($this->format) {
            case 'application/json':
                $this->getResponse()->addHeader('Content-type', 'application/json; charset=utf-8');
                $this->getResponse()->sendHeaders();
                $options = 0;
                if (defined('JSON_PRETTY_PRINT')) {
                    $options &= JSON_PRETTY_PRINT;
                }

                if (defined('JSON_UNESCAPED_UNICODE')) {
                    $options &= JSON_UNESCAPED_UNICODE;
                }

                if (false && version_compare(PHP_VERSION, '5.4', '>=')) {
                    print json_encode($data, $options);
                } else {
                    print json_encode($data);
                }
                break;
            case 'xml':
                print 'XML not implemented';
                break;
            default:
                print 'unknown format '.$this->format;
                break;
        }
    }

    protected function getProfile($items)
    {
        foreach ($items as $item) {
            if (!empty($item['profile_id'])) {
                $profile_helper = new shopImportexportHelper('yandexmarket');
                $this->profile = $profile_helper->getConfig($item['profile_id']);
            }
        }
    }
}
