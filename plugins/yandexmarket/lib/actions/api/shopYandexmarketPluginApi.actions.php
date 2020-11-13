<?php
// Файл отмечен на удаление. Удалить после 01.10.2021
//
//class shopYandexmarketPluginApiActions extends waActions
//{
//    private $format = 'application/json';
//
//    /** @var array */
//    private $campaign;
//    private static $templates = array(
//        'shipping' => 'shipping.%s.%s',
//        'outlet'   => 'outlet.%s',
//    );
//
//    protected function preExecute()
//    {
//        //TODO setup number formats into dot
//        parent::preExecute();
//    }
//
//    public function helloAction()
//    {
//        echo "Ложки не существует";
//    }
//
//    public function cartAction()
//    {
//        /**
//         * @link https://tech.yandex.ru/market/partner/doc/dg/reference/post-cart-docpage/
//         */
//
//        $order = $this->getApiRequest();
//
//        $items = array();
//
//        $total = 0;
//
//        $delivery = !empty($order->shipping_address['is_home_region']) || empty($this->campaign['local_delivery_only']);
//
//        $pickup_only = false;
//        $pickup_allowed = !empty($this->campaign['pickup']);
//
//        foreach ($order->items as $item) {
//            $pickup_allowed = $pickup_allowed && (!isset($item['pickup']) || !empty($item['pickup']));
//            if (isset($item['delivery']) && empty($item['delivery'])) {
//                $pickup_only = true;
//            }
//        }
//
//
//        foreach ($order->items as $item) {
//            $item_pickup = (!isset($item['pickup']) || !empty($item['pickup'])) && !empty($this->campaign['pickup']);
//            $item_shipping = (!isset($item['delivery']) || !empty($item['delivery'])) && !empty($this->campaign['delivery']);
//            $item['_delivery'] = $delivery && ($item_shipping || $item_pickup) && !empty($item['sku_id']);
//
//            $items[] = $this->workupCartItem($item);
//
//            if (isset($item['delivery']) && empty($item['delivery'])) {
//                $pickup_only = true;
//            }
//            if ($item['_delivery']) {
//                $total += floatval($item['price']) * (int)$item['quantity'];
//            }
//            $order->total = $total;
//            unset($item);
//            unset($item_pickup);
//            unset($item_shipping);
//        }
//
//        $carriers = array();
//        $payments = array();
//
//        if (count($items) && $delivery) {
//            $outlets_added = false;
//
//            if (!empty($this->campaign['delivery']) && !$pickup_only) {
//                $carriers += $this->getDeliveryServices($order);
//            }
//
//            if ($pickup_allowed) {
//                $outlets_added = count($carriers);
//                $carriers += $this->getPickupServices($order);
//                $outlets_added = count($carriers) > $outlets_added;
//            }
//
//            if ($outlets_added) {
//                //XXX fix it!!!
//                $payments[] = 'CASH_ON_DELIVERY';
//            }
//
//            foreach ($carriers as &$carrier) {
//                if (!empty($carrier['paymentMethods'])) {
//                    $payments = array_merge($payments, $carrier['paymentMethods']);
//                }
//            }
//
//            $payments = array_unique($payments);
//            // временно не используется.
//            //'SHOP_PREPAID' — предоплата напрямую магазину (только для Украины).
//        }
//
//        $cart = array(
//            'items'           => $items,
//            'deliveryOptions' => array_values($carriers),
//            'paymentMethods'  => array_values($payments),
//        );
//
//        if (!empty($this->campaign['tax_system'])) {
//            $cart['taxSystem'] = $this->campaign['tax_system'];
//        }
//
//        $response = compact('cart');
//
//        if (false) {
//            waLog::log(var_export(compact('order', 'array'), true), 'shop/plugins/yandexmarket/api.cart.debug.log');
//        }
//        $this->sendApiResponse($response);
//    }
//
//    public function orderAcceptAction()
//    {
//        $raw_order = $this->getApiRequest(true);
//        $order_id = null;
//        if ($raw_order->shipping_address['is_home_region'] || empty($this->campaign['local_delivery_only'])) {
//
//            //XXX check over_sell
//            $order = array(
//                'contact'  => $raw_order->contact,
//                'items'    => array(),
//                'currency' => $raw_order->currency,
//                'params'   => array(),
//            );
//
//            foreach ($raw_order['items'] as $item) {
//                if ($item['quantity']) {
//
//                    $order['items'][] = array(
//                        'name'           => ifset($item['name'], $item['raw_data']['offerName']),
//                        'currency'       => $raw_order->currency,
//                        'type'           => 'product',
//                        'sku_id'         => $item['sku_id'],
//                        'product_id'     => $item['product_id'],
//                        'price'          => $item['price'],
//                        'purchase_price' => $item['purchase_price'],
//                        'quantity'       => $item['quantity'],
//                        'tax_id'         => ifset($item['tax_id']),
//                    );
//                }
//            }
//
//            $order['discount'] = shopDiscounts::apply($order);
//
//            # it's yandex.market server data
//            $order['params']['ip'] = waRequest::getIp();
//            $order['params']['user_agent'] = waRequest::getUserAgent();
//
//            $order['params']['sales_channel'] = 'plugin_yandexmarket:';
//
//
//            $order['comment'] = $raw_order->description;
//
//            # to do use other sources!
//            $routing_url = wa()->getRouting()->getRootUrl();
//            $order['params']['storefront'] = wa()->getConfig()->getDomain().($routing_url ? '/'.$routing_url : '');
//
//            if ($raw_order->shipping_id || $raw_order->shipping_name) {
//                if ($raw_order->shipping_id) {
//                    $order['params']['shipping_id'] = $raw_order->shipping_id;
//                }
//
//                if ($raw_order->shipping_plugin) {
//                    $order['params']['shipping_plugin'] = $raw_order->shipping_plugin;
//                }
//
//                $order['params']['shipping_name'] = $raw_order->shipping_name;
//                $order['params']['shipping_rate_id'] = $raw_order->shipping_rate_id;
//
//                if ($raw_order->outlet_id) {
//                    $order['params']['yandexmarket.outlet_id'] = $raw_order->outlet_id;
//                }
//                $order['shipping'] = $raw_order->shipping;
//            } else {
//                $order['shipping'] = 0;
//            }
//
//            if ($raw_order->shipping_est_delivery) {
//                $order['params']['shipping_est_delivery'] = $raw_order->shipping_est_delivery;
//            }
//
//            if ($raw_order->payment_name) {
//                $order['params']['payment_name'] = $raw_order->payment_name;
//            }
//
//            $order['params']['shipping_address.country'] = $raw_order->shipping_address['country'];
//            $order['params']['shipping_address.region'] = $raw_order->shipping_address['region'];
//            $order['params']['shipping_address.city'] = $raw_order->shipping_address['city'];
//            $order['params']['shipping_address.street'] = $raw_order->shipping_address['street'];
//            $order['params']['shipping_address.zip'] = $raw_order->shipping_address['zip'];
//            //TODO subway
//
//            $order['params']['yandexmarket.id'] = $raw_order->yandex_id;
//            if ($raw_order->outlet_id) {
//                $order['params']['yandexmarket.outlet_id'] = $raw_order->outlet_id;
//            }
//            $order['params']['yandexmarket.campaign_id'] = $raw_order->campaign_id;
//
//            if ($raw_order->id) {
//                $order_id = $raw_order->id;
//            } else {
//                $workflow = new shopWorkflow();
//                $workflow_action = $workflow->getActionById('create');
//                /**
//                 * @var shopWorkflowCreateAction $workflow_action
//                 */
//                $order_id = $workflow_action->run($order);
//            }
//        }
//
//        if ($order_id) {
//            $array = array(
//                'order' => array(
//                    'accepted' => true,
//                    'id'       => (string)$order_id,
//                ),
//            );
//        } else {
//            $array = array(
//                'order' => array(
//                    'accepted' => false,
//                    'reason'   => 'OUT_OF_DATE',
//                ),
//            );
//        }
//        $this->sendApiResponse($array);
//    }
//
//    public function orderStatusAction()
//    {
//        $raw_order = $this->getApiRequest(true);
//        if ($raw_order->id) {
//            $action_id = null;
//            $params = null;
//            $callback_params = null;
//            switch ($raw_order->status) {
//                case 'CANCELLED':# заказ отменен
//                    $callback_params = array(
//                        'order_id'    => $raw_order->id,
//                        'plugin'      => 'shop:yandexmarket',
//                        'currency_id' => $raw_order->currency,
//                        'amount'      => $raw_order->total,
//                        'view_data'   => sprintf('%s: %s', $raw_order->sub_status, $raw_order->sub_status_description),
//                        'state'       => waPayment::STATE_CANCELED,
//                    );
//
//                    $params = $raw_order->id;
//                    $action_id = 'delete';
//                    break;
//                case 'RESERVED': # заказ в резерве (ожидается подтверждение от пользователя);
//                case 'PROCESSING':# заказ можно выполнять
//                case 'UNPAID':# заказ оформлен, но еще не оплачен (если выбрана оплата при оформлении)
//
//                    $callback_params = array(
//                        'id'          => $raw_order->yandex_id,
//                        'order_id'    => $raw_order->id,
//                        'plugin'      => 'shop:yandexmarket',
//                        'currency_id' => $raw_order->currency,
//                        'amount'      => $raw_order->total,
//                        'view_data'   => 'Заказ в обработке',
//                        'state'       => 'UNPAID',
//                    );
//
//                    #update shipping address data
//                    $order_params = array();
//                    $order_params['shipping_address.country'] = $raw_order->shipping_address['country'];
//                    $order_params['shipping_address.region'] = $raw_order->shipping_address['region'];
//                    $order_params['shipping_address.city'] = $raw_order->shipping_address['city'];
//                    $order_params['shipping_address.street'] = $raw_order->shipping_address['street'];
//                    $order_params['shipping_address.zip'] = $raw_order->shipping_address['zip'];
//                    $order_params_model = new shopOrderParamsModel();
//                    $order_params_model->set($raw_order->id, $order_params, false);
//
//                    if ($raw_order->paid_datetime) {
//                        $action_id = 'pay';
//                        $callback_params['view_data'] = 'Заказ оплачен';
//                        $callback_params['state'] = waPayment::STATE_VERIFIED;
//                    } else {
//                        if ($raw_order->status == 'RESERVED') {
//                            $callback_params['view_data'] = 'Заказ зарезевирован';
//                        }
//                    }
//
//                    $order = array(
//                        'comment' => $raw_order->description,
//                    );
//
//                    if ($raw_order->contact_id) {
//                        if ($this->getPlugin()->getSettings('contact_id') != $raw_order->contact_id) {
//                            $order['contact_id'] = $raw_order->contact_id;
//                        }
//                    }
//
//                    $order_model = new shopOrderModel();
//                    $order_model->updateById($raw_order->id, $order);
//
//                    if ($raw_order->contact_id) {
//                        $customer_model = new shopCustomerModel();
//                        $customer_data = array(
//                            'number_of_orders' => $order_model->countByField('contact_id', $raw_order->contact_id),
//                            'last_order_id'    => $order_model->select('MAX(id)')->where('contact_id = ?', $raw_order->contact_id)->fetchField(),
//                            'total_spent'      => $order_model->getTotalSalesByContact($raw_order->contact_id),
//                        );
//
//                        $customer_model->updateById($raw_order->contact_id, $customer_data);
//                    }
//
//                    $params = $raw_order->id;
//
//                    break;
//                default:
//                    //XXX log error — unknown order state
//                    break;
//            }
//
//            if ($callback_params) {
//                $workflow = new shopWorkflow();
//
//                $workflow->getActionById('callback')->run($callback_params);
//                if ($action_id) {
//                    $workflow->getActionById($action_id)->run($params);
//                }
//            }
//        } else {
//            throw new waException(sprintf('Order %s not found', $raw_order->id), 404);
//        }
//    }
//
//    private function checkAuth()
//    {
//        /**
//         * @see https://tech.yandex.ru/market/partner/doc/dg/concepts/identification-docpage/
//         */
//        $token = strtolower(waRequest::server('Authorization', waRequest::get('auth-token')));
//        $valid_token = null;
//        if (!empty($token)) {
//            $valid_token = ifset($this->campaign['market_token']);
//            $valid_token = strtolower($valid_token);
//        }
//        if (empty($token) || ($token != $valid_token)) {
//            waLog::log('Invalid request with token '.var_export($token, true), 'shop/plugins/yandexmarket/api.request.error.log');
//            throw new waException('Access forbidden', 403);
//        }
//    }
//
//    /**
//     * @return shopYandexmarketPlugin
//     * @throws waException
//     */
//    private function getPlugin()
//    {
//        static $plugin;
//        if (empty($plugin)) {
//            $plugin = wa('shop')->getPlugin('yandexmarket');
//            /**
//             * @var shopYandexmarketPlugin $plugin
//             */
//        }
//        return $plugin;
//    }
//
//    /**
//     * @param bool $save_contact
//     * @return shopYandexmarketPluginOrder
//     * @throws Exception
//     * @throws waException
//     */
//    private function getApiRequest($save_contact = false)
//    {
//        if (($debug = waRequest::get('debug')) && file_exists($path = dirname(dirname(dirname(__FILE__))).'/config/example/api.cart.json')) {
//            $example = json_decode(file_get_contents($path), true);
//            $raw = json_encode(ifset($example[$debug], array()));
//        } elseif (isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
//            $raw = !empty($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : null;
//        } else {
//            $raw = implode("", file('php://input'));
//        }
//
//
//        $content_type = waRequest::server('content_type', 'application/json;charset=utf-8');
//        if (strpos($content_type, ';')) {
//            $content_type = explode(';', $content_type, 2);
//            $this->format = reset($content_type);
//        }
//
//        switch ($this->format) {
//            case 'application/json':
//                $json = json_decode($raw, true);
//
//                if (!$json || !is_array($json)) {
//                    throw new waException('Invalid data');
//                }
//                $order = shopYandexmarketPluginOrder::createFromJson($json, $this->getPlugin(), $save_contact);
//
//                break;
//            case 'application/xml':
//                if (!empty($raw)) {
//                    libxml_use_internal_errors(true);
//                    libxml_clear_errors();
//                    try {
//                        $xml = new SimpleXMLElement($raw);
//                        $errors = libxml_get_errors();
//                        if ($errors) {
//                            foreach ($errors as $error) {
//                                /**
//                                 * @var LibXMLError $error
//                                 */
//                                $error->code;
//                            }
//                        }
//                    } catch (Exception $ex) {
//                        throw $ex;
//                    }
//                } else {
//                    throw new waException('Empty POST');
//                }
//                $order = shopYandexmarketPluginOrder::createFromXml($xml, $this->getPlugin());
//                break;
//            default:
//                $order = new shopYandexmarketPluginOrder();
//                break;
//        }
//
//
//        $campaign_model = new shopYandexmarketCampaignsModel();
//
//        $this->campaign = $campaign_model->get($order->campaign_id);
//        $this->campaign['id'] = (int)$order->campaign_id;
//
//        $this->checkAuth();
//
//        if ($order->items && !empty($this->campaign['over_sell'])) {
//            $over_sell = false;
//            $items = $order->items;
//            foreach ($items as &$item) {
//                if (($item['sku_id'] !== null) && ($item['quantity'] < $item['raw_data']['count'])) {
//                    $item['quantity'] = $item['raw_data']['count'];
//                    $item['raw_data']['over_sell'] = $item['raw_data']['count'] - $item['quantity'];
//                    $over_sell = true;
//                }
//                unset($item);
//
//            }
//            $order->items = $items;
//            $order->over_sell = $over_sell;
//        }
//
//        if (!empty($json)) {
//            unset($raw);
//        } else {
//            $server = waRequest::server();
//        }
//
//        waLog::log(var_export(compact('raw', 'server', 'headers', 'json'), true), 'shop/plugins/yandexmarket/api.request.log');
//        return $order;
//    }
//
//    protected function sendApiResponse($data)
//    {
//        switch ($this->format) {
//            case 'application/json':
//                $this->getResponse()->addHeader('Content-type', 'application/json; charset=utf-8');
//                $this->getResponse()->sendHeaders();
//                $options = 0;
//                if (defined('JSON_PRETTY_PRINT')) {
//                    $options &= JSON_PRETTY_PRINT;
//                }
//
//                if (defined('JSON_UNESCAPED_UNICODE')) {
//                    $options &= JSON_UNESCAPED_UNICODE;
//                }
//
//                if (false && version_compare(PHP_VERSION, '5.4', '>=')) {
//                    print json_encode($data, $options);
//                } else {
//                    print json_encode($data);
//                }
//                break;
//            case 'xml':
//                print 'XML not implemented';
//                break;
//            default:
//                print 'unknown format '.$this->format;
//                break;
//        }
//    }
//
//    private function getCalendarMap($to_days = 32, $order_before = null)
//    {
//        static $maps = array();
//        $key = md5(var_export($order_before, true));
//        $to_days = max(32, $to_days);
//        if (!isset($maps[$key])) {
//            $maps[$key] = array();
//        }
//        $map = &$maps[$key];
//        if (($map === null) || (count($map) <= $to_days)) {
//            $map = array();
//            $now = time();
//            $time = (int)date('G', $now);
//            $week_day = (int)date('N', $now) - 1;
//            $timezone = ifset($this->campaign['timezone']);
//            $offset = 0;
//
//            if (!isset($this->campaign['schedule']) || ((time() - strtotime($this->campaign['schedule']['period']['fromDate'])) > 3600 * 48)) {
//                $options = array(
//                    'settings'    => true,
//                    'campaign_id' => $this->campaign['id'],
//                );
//
//                $campaigns = $this->getPlugin()->getCampaigns($options);
//
//                $campaign = ifset($campaigns[$this->campaign['id']], array());
//                if (!empty($campaign['settings']['localRegion']['schedule'])) {
//                    $this->campaign['schedule'] = $campaign['settings']['localRegion']['delivery']['schedule'];
//                } else {
//                    $this->campaign['schedule'] = array();
//                }
//            }
//
//            $holidays = ifset($this->campaign['schedule']['totalHolidays'], array());
//
//            if ($timezone) {
//                $time = (int)waDateTime::date('G', $now, $timezone);
//                $week_day = (int)waDateTime::date('N', $now, $timezone) - 1;
//            }
//
//            if ($order_before === null) {
//                $order_before = $this->campaign;
//            }
//
//            if (ifset($order_before['order_before_mode']) == 'per-day') {
//                $working_days = ifempty($order_before['order_before_per_day'], array_fill(0, 7, 17));
//            } else {
//                $working_days = array_fill(0, 7, min(24, max(1, ifset($order_before['order_before'], 24))));
//            }
//
//
//            if ($working_days) {
//                while ($time >= ifset($working_days[($week_day + $offset) % 7], 0)) {
//                    ++$offset;
//                    $time = null;
//                }
//            }
//
//            if (!empty($working_days)) {
//
//                $day = 0;
//                $offset_day = $offset;
//                while ($day <= $to_days) {
//                    $current_week_day = ($offset_day + $week_day) % 7;
//                    $current_date = strtotime(sprintf('+%d days', $offset_day));
//
//                    if ($timezone) {
//                        $date = waDateTime::date('d-m-Y', $current_date, $timezone);
//                    } else {
//                        $date = date('d-m-Y', $current_date);
//                    }
//
//                    if (!in_array($date, $holidays) && !empty($working_days[$current_week_day])) {
//                        $map[$day++] = $offset_day;
//                    }
//
//                    ++$offset_day;
//                };
//            }
//        }
//        return $map;
//    }
//
//    /**
//     * @param array $defaults
//     * @param shopYandexmarketPluginOrder $order
//     * @param bool $custom_priority
//     * @return array
//     */
//    protected function formatDeliveryDates($defaults, $order = null, $custom_priority = false)
//    {
//        if ($order->over_sell) {
//            $interval = shopYandexmarketPlugin::getDays(ifempty($this->campaign['estimate_over_sell'], '32'));
//        } else {
//            if (!empty($order->shipping_address['is_home_region'])) {
//                $interval = ifset($defaults['estimate'], '32');
//            } else {
//                $interval = ifset($defaults['estimate_ext'], '32');
//            }
//            if ($interval === '') {
//                $interval = array(32);
//            } else {
//                $interval = shopYandexmarketPlugin::getDays($interval);
//            }
//        }
//
//        if (!count($interval)) {
//            $interval = array(32);
//        }
//        $from = min($interval);
//        $to = max($interval);
//
//        if ($order) {
//            if ($order->delivery_from !== null) {
//                if ($custom_priority) {
//                    $from = $order->delivery_from;
//                } else {
//                    $from = max($order->delivery_from, $from);
//                }
//            }
//            if ($order->delivery_before) {
//                if ($custom_priority) {
//                    $to = $order->delivery_before;
//                } else {
//                    $to = max($order->delivery_before, $to);
//                }
//            }
//        }
//        $order_before = null;
//        if (!empty($defaults['order_before_mode'])) {
//            $order_before = $defaults;
//        }
//
//        $map = $this->getCalendarMap(max($from, $to), $order_before);
//
//        if (!empty($defaults['cal'])) {
//            $from += $map[0];
//            $to += $map[0];
//        } else {
//            $from = $map[$from];
//            $to = $map[$to];
//        }
//
//        return array(
//            'fromDate' => date('d-m-Y', strtotime(sprintf('+%d days', $from))),
//            'toDate'   => date('d-m-Y', strtotime(sprintf('+%d days', $to))),
//        );
//    }
//
//    private function fixVat($vat)
//    {
//        $fix = false;
//        if (empty($this->campaign['payment']['YANDEX'])) {
//            $vat = null;
//        } elseif ($fix) {
//            switch ($vat) {
//                case 'NO_VAT':
//                    break;
//                default:
//                    switch (ifset($this->campaign['tax_system'], '')) {
//                        case 'OSN':
//                            break;
//                        case 'USN':
//                        case 'USN_MINUS_COST':
//                            $vat = 'NO_VAT';
//                            break;
//                        default:
//                            $vat = null;
//                            break;
//                    }
//                    break;
//            }
//        }
//        return $vat;
//    }
//
//    private function workupCarrier(&$carrier)
//    {
//        $vat = $this->fixVat(ifset($this->campaign['shipping_tax'], 'NO_VAT'));
//        if (!empty($vat)) {
//            $carrier['vat'] = $vat;
//        }
//        return $carrier;
//    }
//
//    private function workupCartItem($item)
//    {
//        $vat = empty($this->campaign['offer_tax']) ? $item['vat'] : $this->campaign['offer_tax'];
//        $_item = array(
//            'feedId'   => $item['raw_data']['feedId'],
//            'offerId'  => $item['raw_data']['offerId'],
//            'price'    => floatval($item['price']),
//            'vat'      => $this->fixVat($vat),
//            'count'    => (int)$item['quantity'],
//            'delivery' => $item['_delivery'],
//        );
//        if (empty($_item['vat'])) {
//            unset($_item['vat']);
//        }
//
//        return $_item;
//    }
//
//    private function workupOrderItems($items)
//    {
//        $product_ids = array();
//        foreach ($items as $item) {
//            $product_ids[] = $item['product_id'];
//        }
//        $product_ids = array_unique($product_ids);
//        if ($product_ids) {
//            $feature_model = new shopFeatureModel();
//            $f = $feature_model->getByCode('weight');
//            if (!$f) {
//                $values = array();
//            } else {
//                $values_model = $feature_model->getValuesModel($f['type']);
//                $values = $values_model->getProductValues($product_ids, $f['id']);
//            }
//
//            foreach ($items as &$item) {
//                if (isset($values['skus'][$item['sku_id']])) {
//                    $w = $values['skus'][$item['sku_id']];
//                } else {
//                    $w = isset($values[$item['product_id']]) ? $values[$item['product_id']] : 0;
//                }
//
//                $item['weight'] = $w;
//                unset($item);
//            }
//        }
//        return $items;
//    }
//
//    /**
//     * @param shopYandexmarketPluginOrder $order
//     * @return array[string]
//     */
//    private function getDeliveryServices($order)
//    {
//        $carriers = array();
//        //get product params for delivery dates
//
//        if (!empty($this->campaign['shipping_methods'])) {
//            $profile_shipping_methods = $this->campaign['shipping_methods'];
//            $shipping_methods = array_keys($profile_shipping_methods);
//            foreach ($shipping_methods as &$shipping_id) {
//                $shipping_id = preg_replace('@\..+$@', '', $shipping_id);
//                unset($shipping_id);
//            }
//
//            $shipping_methods = array_unique($shipping_methods);
//            $items = $this->workupOrderItems($order->items);
//
//            $debug = array(
//                'shipping_methods'         => $shipping_methods,
//                'profile_shipping_methods' => $profile_shipping_methods,
//                'address'                  => $order->shipping_address,
//                'items'                    => $items,
//                'rates'                    => array(),
//                'payment_methods'          => array(),
//            );
//
//
//            $plugin_model = new shopPluginModel();
//            $methods = $plugin_model->listPlugins(shopPluginModel::TYPE_SHIPPING);
//
//            foreach ($shipping_methods as $shipping_id) {
//                if ($shipping_id == shopShipping::DUMMY) {//XXX
//                    $defaults = ifset($profile_shipping_methods[$shipping_id], array());
//
//                    $payment_methods = $this->getPaymentMethods($defaults);
//
//                    if (empty($payment_methods)) {
//                        $debug['payment_methods'][] = $shipping_id;
//                    } elseif (!empty($order->shipping_address['is_home_region'])) {
//
//                        if (!empty($this->campaign['deliveryIncluded'])) {
//                            $price = 0;
//                        } else {
//                            $count = 0;
//                            foreach ($order->items as $item) {
//                                if ($item['quantity'] > 0) {
//                                    ++$count;
//                                }
//                                if ($count > 1) {
//                                    break;
//                                }
//                            }
//                            if (($count == 1) && ($order->delivery_cost !== null)) {
//                                $price = intval($order->delivery_cost);
//                            } else {
//                                $price = intval(ifset($defaults['cost']));
//                            }
//                        }
//
//
//                        $carriers['dummy'] = array(
//                            'id'             => 'courier',
//                            'type'           => ifempty($defaults['type'], 'DELIVERY'),
//                            'serviceName'    => ifempty($defaults['name'], 'Курьер'),
//                            'price'          => $price,
//                            'dates'          => $this->formatDeliveryDates($defaults, $order, true),
//                            'paymentMethods' => $payment_methods,
//                        );
//                        $this->workupCarrier($carriers['dummy']);
//                    } else {
//                        $debug['rates'][$shipping_id] = 'Only for home region';
//                    }
//
//                } else {
//                    try {
//                        if (isset($methods[$shipping_id]) && !empty($methods[$shipping_id]['available'])) {
//                            $shipping_info = $methods[$shipping_id];
//                            $shipping_info['id'] = $shipping_id;
//
//                            $rates = $this->getPluginRates($order, $items, $shipping_info);
//
//                            $debug['rates'][$shipping_id] = $rates;
//                            $method_defaults = ifset($profile_shipping_methods[$shipping_id], array());
//
//                            //XXX CPA объединять сервисы доставки как точки продаж или как различные способы доставки
//                            if ($rates && is_array($rates)) {
//                                foreach ($rates as $rate_id => $rate) {
//                                    if ($rate['rate'] !== null) {
//                                        $rate_id = ifset($rate['id'], $rate_id);
//                                        $defaults = ifset($profile_shipping_methods[$shipping_id.'.'.$rate_id], array()) + $method_defaults;
//
//                                        #delivery price
//                                        if (!empty($this->campaign['deliveryIncluded'])) {
//                                            $rate['rate'] = 0;
//                                        } elseif (isset($defaults['cost'])
//                                            && (!in_array($defaults['cost'], array('', 'false', false, null), true))
//                                        ) {
//                                            $rate['rate'] = $defaults['cost'];
//                                        } elseif (is_array($rate['rate'])) {
//                                            $rate['rate'] = max($rate['rate']);
//                                        }
//
//                                        $id = sprintf(self::$templates['shipping'], $shipping_id, $rate_id);
//                                        if ($payment_methods = $this->getPaymentMethods($defaults)) {
//                                            $name = sprintf('%s %s', $shipping_info['name'], ifset($rate['name']));
//                                            $name = ifset($defaults['name'], $name);
//                                            $carriers[$id] = array(
//                                                'id'             => $id,
//                                                'serviceName'    => mb_substr(trim($name), 0, 50, 'utf-8'),
//                                                'type'           => ifempty($defaults['type'], 'DELIVERY'),
//                                                'price'          => round($rate['rate']),
//                                                'dates'          => $this->formatDeliveryDates($defaults, $order),
//                                                'paymentMethods' => $payment_methods,
//                                            );
//
//                                            $this->workupCarrier($carriers[$id]);
//
//                                            if (($carriers[$id]['type'] == 'POST')
//                                                && ($this->campaign['payment']['YANDEX'])
//                                                && !in_array('YANDEX', $payment_methods)
//                                            ) {
//                                                $carriers[$id]['paymentAllow'] = false;
//                                            }
//
//                                            if ($carriers[$id]['type'] == 'PICKUP') {
//                                                $this->campaign['pickup_map'];
//                                                $outlets = array();
//                                                foreach ($this->campaign['pickup_map'] as $outlet_id => $outlet_shipping_id) {
//                                                    if ($outlet_shipping_id == $shipping_id) {
//                                                        $outlets[] = array(
//                                                            'id' => $outlet_id,
//                                                        );
//                                                    }
//                                                }
//                                                if ($outlets) {
//                                                    $carriers[$id]['outlets'] = $outlets;
//                                                } else {
//                                                    $carriers[$id]['type'] = 'DELIVERY';
//                                                }
//                                            }
//                                        } else {
//                                            $debug['payment_methods'][] = $id;
//                                        }
//                                    }
//                                }
//                            }
//                        }
//                    } catch (waException $ex) {
//                        $message = $ex->getMessage();
//                        $log = var_export(compact('message', 'shipping_id'), true);
//                        waLog::log($log, 'shop/plugins/yandexmarket/shipping.error.log');
//
//                        $debug['rates'][$shipping_id] = $message;
//                    }
//                }
//
//            }
//
//            $debug['carriers'] = $carriers;
//            if (false) {
//                waLog::log(var_export($debug, true), 'shop/plugins/yandexmarket/shipping.debug.log');
//            }
//        }
//        return $carriers;
//    }
//
//    /**
//     * @param shopYandexmarketPluginOrder $order
//     * @return array
//     */
//    private function getPickupServices($order)
//    {
//        $carriers = array();
//        //XXX CPA add setting: allow add pickup points from profile
//        $outlets = null;
//        try {
//            $outlets = $this->getPlugin()->getOutlets($order->campaign_id, $order->region_id);
//        } catch (waException $ex) {
//            $message = $ex->getMessage();
//            waLog::log(sprintf('%d: %s', $order->campaign_id, $message), 'shop/plugins/yandexmarket/api.error.log');
//        }
//        if ($outlets) {
//            # PICKUP delivery options
//            foreach ($outlets as $outlet) {
//                if (true
//                    && (ifset($outlet['status']) != 'FAILED')
//                    && (ifset($outlet['visibility']) != 'HIDDEN ')
//                    && in_array(ifset($outlet['type']), array('MIXED', 'DEPOT'), true)
//                    && !isset($this->campaign['pickup_map'][$outlet['id']])
//                ) {
//
//                    $price = 0;
//                    $defaults = array('estimate' => '32');
//                    if (!empty($outlet['deliveryRules'])) {
//                        foreach ($outlet['deliveryRules'] as $delivery_rule) {
//                            $match = false;
//                            if (empty($delivery_rule['priceFrom']) || (floatval($delivery_rule['priceFrom']) >= $order->total)) {
//                                $match = true;
//                            }
//                            if (!empty($delivery_rule['priceTo']) && (floatval($delivery_rule['priceTo']) > $order->total)) {
//                                $match = false;
//                            }
//                            if ($match) {
//                                $price = floatval($delivery_rule['cost']);
//
//                                $defaults['estimate'] = implode('-', array(
//                                    ifset($delivery_rule['minDeliveryDays'], 32),
//                                    ifset($delivery_rule['maxDeliveryDays'], 32),
//                                ));
//                            }
//                        }
//                    }
//
//                    //TODO parse dates
//                    $address = '';
//                    if (!empty($outlet['address'])) {
//                        //TODO check max ServiceName length
//                        //$address = implode(', ', $outlet['address']);
//                    }
//                    //TODO group outlets by delivery service
//                    $id = sprintf(self::$templates['outlet'], $outlet['id']);
//                    $carriers[$id] = array(
//                        'id'          => $id,
//                        'serviceName' => mb_substr(trim(sprintf('%s %s', $outlet['name'], $address)), 0, 50),
//                        'type'        => 'PICKUP',
//                        'price'       => $price,
//                        'dates'       => $this->formatDeliveryDates($defaults, $order),
//                        'outlets'     => array(
//                            array(
//                                'id' => $outlet['id'],
//                            ),
//                        ),
//                    );
//
//                    $this->workupCarrier($carriers[$id]);
//                }
//            }
//        }
//        return $carriers;
//    }
//
//    private function getPluginRates($order, &$items, $shipping_info)
//    {
//        static $config;
//        static $round_shipping;
//        if (empty($config)) {
//            $config = wa('shop')->getConfig();
//            /** @var $config shopConfig */
//            $round_shipping = wa('shop')->getSetting('round_shipping');
//        }
//        $rates = false;
//        if ($shipping = shopShipping::getPlugin($shipping_info['plugin'], $shipping_info['id'])) {
//            $plugin_currency = (array)$shipping->allowedCurrency();
//            if ($config->getCurrencies($plugin_currency)) {
//                #prepare total price data
//                $total_price = null;
//                foreach ($items as $item) {
//                    if (!empty($item['price'])) {
//                        $total_price += $item['price'] * (isset($item['quantity']) ? $item['quantity'] : 1);
//                    }
//                    if ($total_price && !in_array($order->currency, $plugin_currency)) {
//                        $total_price = shop_currency($total_price, $order->currency, reset($plugin_currency), false);
//                    }
//                }
//
//
//                #prepare total weight data
//                $weight_unit = $shipping->allowedWeightUnit();
//                foreach ($items as &$item) {
//                    if (!empty($item['weight'])) {
//                        if (empty($item['original_weight'])) {
//                            $item['original_weight'] = $item['weight'];
//                        }
//                        $item['weight'] = shopDimension::getInstance()->convert($item['original_weight'], 'weight', $weight_unit);
//                    }
//                }
//                unset($item);
//
//                #get actual rates
//                $rates = $shipping->getRates($items, $order->shipping_address, compact('total_price'));
//                if ($rates && is_array($rates)) {
//                    foreach ($rates as &$rate) {
//                        if ($rate['rate']) {
//                            if (ifset($rate['currency'], $plugin_currency) != $order->currency) {
//                                $rate['raw_rate'] = $rate['rate'];
//                                $rate['raw_currency'] = $rate['currency'];
//                                $rate['rate'] = shop_currency($rate['rate'], $rate['currency'], $order->currency, false);
//
//                                if ($rate['rate'] && $round_shipping) {
//                                    $rate['rate'] = shopRounding::roundCurrency($rate['rate'], $rate['currency']);
//                                }
//
//                                $rate['currency'] = $order->currency;
//                            }
//                        }
//                        unset($rate);
//                    }
//                }
//            }
//        }
//        return $rates;
//    }
//
//    private function getPaymentMethods($defaults)
//    {
//        $payment_methods = array();
//
//        if (!empty($this->campaign['payment']['CASH_ON_DELIVERY']) && !empty($defaults['cash'])) {
//            $payment_methods[] = 'CASH_ON_DELIVERY';
//        }
//
//        if (!empty($this->campaign['payment']['CARD_ON_DELIVERY']) && !empty($defaults['card'])) {
//            $payment_methods[] = 'CARD_ON_DELIVERY';
//        }
//
//        if (!empty($this->campaign['payment']['YANDEX']) && (empty($defaults['!yandex']) || (ifset($defaults['type']) != 'POST'))) {
//            $payment_methods[] = 'YANDEX';
//        }
//
//        return $payment_methods;
//    }
//}
