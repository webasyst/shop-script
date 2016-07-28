<?php

class shopNotifications
{
    protected static $model;

    /**
     * @return shopNotificationModel
     */
    protected static function getModel()
    {
        if (!self::$model) {
            self::$model = new shopNotificationModel();
        }
        return self::$model;
    }

    /**
     * Sends a notification set up for specified event in store settings.
     *
     * @param string $event Event type id; e.g., 'order.create', 'order.ship'
     * @param array $data Order data array, keys 'order', 'customer' and 'action_data'
     */
    public static function send($event, $data)
    {
        $notifications = self::getModel()->getByEvent($event, true);
        self::prepareData($data);

        if ($notifications) {
            foreach ($notifications as $n) {
                if (!$n['source'] || ($n['source'] == $data['source'])) {
                    $method = 'send'.ucfirst($n['transport']);
                    if (method_exists('shopNotifications', $method)) {
                        self::$method($n, $data);
                    }
                }
            }
        }

        self::sendPushNotifications($event, $data);
    }

    /**
     * Sends a test notification.
     *
     * @param int $id Notification id stored in table shop_notification
     * @param array $data Order data array, keys 'order', 'customer' and 'action_data'
     * @param string|null $to Recipient address/number. If not specified, recipient address/number from notification parameters is used.
     */
    public static function sendOne($id, $data, $to = null)
    {
        $n = self::getModel()->getOne($id);
        if (!$n) {
            return;
        }
        self::prepareData($data);

        $method = 'send'.ucfirst($n['transport']);
        if (method_exists('shopNotifications', $method)) {
            if ($to !== null) {
                $n['to'] = $to;
            }
            self::$method($n, $data);
        }
    }

    protected static function prepareData(&$data)
    {
        $params_model = new shopOrderParamsModel();
        $data['order']['params'] = $params_model->get($data['order']['id'], true);

        $items_model = new shopOrderItemsModel();
        $data['order']['items'] = $items_model->getItems($data['order']['id']);

        // Routing params to generate full URLs to products
        $source = 'backend';
        $storefront_route = null;
        $storefront_domain = null;
        if (isset($data['order']['params']['storefront'])) {
            $storefront = $data['order']['params']['storefront'];
            if (substr($storefront, -1) === '/') {
                $source = $storefront.'*';
            } else {
                $source = $storefront.'/*';
            }

            foreach (wa()->getRouting()->getByApp('shop') as $domain => $routes) {
                foreach ($routes as $r) {
                    if (!isset($r['url'])) {
                        continue;
                    }
                    $st = rtrim(rtrim($domain, '/').'/'.$r['url'], '/.*');
                    if ($st == $storefront) {
                        $storefront_route = $r;
                        $storefront_domain = $domain;
                        break 2;
                    }
                }
            }
        }
        $data['source'] = $source;

        // Products info
        $product_ids = array();
        foreach ($data['order']['items'] as $i) {
            $product_ids[$i['product_id']] = 1;
        }
        if ($storefront_domain) {
            $d = 'http://'.$storefront_domain;
        } else {
            $d = rtrim(wa()->getRootUrl(true), '/');
        }
        $collection = new shopProductsCollection('id/'.join(',', array_keys($product_ids)));
        $products = $collection->getProducts('*,image');
        foreach ($products as &$p) {
            $p['frontend_url'] = wa()->getRouteUrl('shop/frontend/product', array(
                'product_url' => $p['url'],
            ), true, $storefront_domain, $storefront_route['url']);
            if (!empty($p['image'])) {
                $p['image']['thumb_url'] = $d.$p['image']['thumb_url'];
                $p['image']['big_url'] = $d.$p['image']['big_url'];
                $p['image']['crop_url'] = $d.$p['image']['crop_url'];
            }
        }
        unset($p);

        // URLs and products for order items
        foreach ($data['order']['items'] as &$i) {
            if (!empty($i['file_name'])) {
                $i['download_link'] = wa()->getRouteUrl('shop/frontend/myOrderDownload', array(
                    'id'   => $data['order']['id'],
                    'code' => $data['order']['params']['auth_code'],
                    'item' => $i['id'],
                ), true, $storefront_domain, $storefront_route['url']);
            }
            if (!empty($products[$i['product_id']])) {
                $i['product'] = $products[$i['product_id']];
            } else {
                $i['product'] = array();
            }
        }
        unset($i);

        // Shipping info
        if (!empty($data['order']['params']['shipping_id'])) {
            try {
                $data['shipping_plugin'] = shopShipping::getPlugin(ifset($data['order']['params']['shipping_plugin']), $data['order']['params']['shipping_id']);
            } catch (waException $e) {
            }
        }


        if (isset($data['order']['params']['signup_url'])) {
            $data['signup_url'] = $data['order']['params']['signup_url'];
            unset($data['order']['params']['signup_url']);
        }

        // normalize customer
        $customer = ifset($data['customer'], new shopCustomer(ifset($data['order']['contact_id'], 0)));
        if (!($customer instanceof shopCustomer)) {
            if ($customer instanceof waContact) {
                $customer = new shopCustomer($customer->getId());
            } elseif (is_array($customer) && isset($customer['id'])) {
                $customer = new shopCustomer($customer['id']);
            } else {
                $customer = new shopCustomer(ifset($data['order']['contact_id'], 0));
            }
        }

        $customer_data = $customer->getCustomerData();
        foreach (ifempty($customer_data, array()) as $field_id => $value) {
            if ($field_id !== 'contact_id') {
                $customer[$field_id] = $value;
            }
        }
        $data['customer'] = $customer;


        // affiliate bonus
        if (shopAffiliate::isEnabled()) {
            $data['is_affiliate_enabled'] = true;
            $data['add_affiliate_bonus'] = shopAffiliate::calculateBonus($data['order']);
        }

        $data['order_url'] = wa()->getRouteUrl('/frontend/myOrderByCode', array('id' => $data['order']['id'], 'code' => ifset($data['order']['params']['auth_code'])), true);

        shopHelper::workupOrders($data['order'], true);

        // empty defaults, to avoid notices
        $empties = self::getDataEmpties();
        $data = self::arrayMergeRecursive($data, $empties);
    }

    private static function getDataEmpties()
    {
        return array(
            'status'               => '',
            'order_url'            => '',
            'signup_url'           => '',
            'add_affiliate_bonus'  => 0,
            'is_affiliate_enabled' => false,
            'order'                => array(
                'id'       => '',
                'currency' => '',
                'items'    => array(),
                'discount' => '',
                'tax'      => '',
                'shipping' => 0,
                'total'    => 0,
                'comment'  => '',
                'params'   => array(
                    'shipping_name'         => '',
                    'shipping_description'  => '',
                    'payment_name'          => '',
                    'payment_description'   => '',
                    'auth_pin'              => '',
                    'storefront'            => '',
                    'ip'                    => '',
                    'user_agent'            => '',
                    'shipping_est_delivery' => '',
                    'tracking_number'       => ''
                )
            ),
            'customer'             => new shopCustomer(0),
            'shipping_address'     => '',
            'billing_address'      => '',
            'action_data'          => array(
                'text'   => '',
                'params' => array(
                    'tracking_number' => ''
                )
            )
        );
    }

    public static function arrayMergeRecursive($merge_to, $merge_from)
    {
        foreach ($merge_from as $key => $value) {
            if (!array_key_exists($key, $merge_to)) {
                $merge_to[$key] = $value;
            } elseif (is_array($merge_to[$key]) && is_array($merge_from[$key])) {
                $merge_to[$key] = self::arrayMergeRecursive($merge_to[$key], $merge_from[$key]);
            }
        }
        return $merge_to;
    }

    protected static function sendEmail($n, $data)
    {
        /**
         * @var shopConfig $config ;
         */
        $config = wa('shop')->getConfig();
        $general = $config->getGeneralSettings();
        if (!empty($n['from'])) {
            $from = $n['from'];
        } else {
            $from = $general['email'];
        }
        /**
         * @var waContact $customer
         */
        $customer = $data['customer'];
        if ($n['to'] == 'customer') {
            $email = $customer->get('email', 'default');
            if (!$email) {
                return;
            }
            $to = array($email);
            $log = sprintf(_w("Notification <strong>%s</strong> sent to customer."), $n['name']);
        } elseif ($n['to'] == 'admin') {
            if (!$general['email']) {
                return;
            }
            $to = array($general['email']);
            $log = sprintf(_w("Notification <strong>%s</strong> sent to store admin."), $n['name']);
        } else {
            $to = explode(',', $n['to']);
            $log = sprintf(_w("Notification <strong>%s</strong> sent to %s."), $n['name'], $n['to']);
        }

        foreach (array('shipping', 'billing') as $k) {
            $address = shopHelper::getOrderAddress($data['order']['params'], $k);
            $formatter = new waContactAddressOneLineFormatter(array('image' => false));
            $address = $formatter->format(array('data' => $address));
            $data[$k.'_address'] = $address['value'];
        }
        $order_id = $data['order']['id'];
        $data['order']['id'] = shopHelper::encodeOrderId($order_id);

        $view = wa()->getView();
        $view->assign($data);
        $subject = $view->fetch('string:'.$n['subject']);
        $body = $view->fetch('string:'.$n['body']);

        $message = new waMailMessage($subject, $body);
        $message->setTo($to);
        if ($n['to'] == 'admin') {
            if ($customer_email = $customer->get('email', 'default')) {
                $message->addReplyTo($customer_email);
            }
        }
        if ($from) {
            $from_name = $general['name'];
            if ($config->getOption('notification_name') == 'route_params') {
                if (wa()->getEnv() == 'frontend') {
                    $from_name = waRequest::param('_name');
                } elseif (!empty($data['order']['params']['storefront'])) {
                    if ($routes = wa()->getRouting()->getByApp('shop')) {
                        foreach ($routes as $domain => $domain_routes) {
                            foreach ($domain_routes as $route) {
                                $route_storefront = $domain;
                                $route_url = waRouting::clearUrl($route['url']);
                                if ($route_url) {
                                    $route_storefront .= '/'.$route_url;
                                }
                                if ($route_storefront == $data['order']['params']['storefront']) {
                                    $from_name = ifempty($route['_name']);
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
            $message->setFrom($from, $from_name);
        }

        if ($message->send()) {
            $order_log_model = new shopOrderLogModel();
            $order_log_model->add(array(
                'order_id'        => $order_id,
                'contact_id'      => null,
                'action_id'       => '',
                'text'            => '<i class="icon16 email"></i> '.$log,
                'before_state_id' => $data['order']['state_id'],
                'after_state_id'  => $data['order']['state_id'],
            ));
        }
    }

    protected static function sendSms($n, $data)
    {
        $general = wa('shop')->getConfig()->getGeneralSettings();
        /**
         * @var waContact $customer
         */
        $customer = ifset($data['customer']);
        $locale = $old_locale = null;
        if ($n['to'] == 'customer') {
            if (!$customer) {
                return;
            }
            $locale = $customer->getLocale();
            $to = $customer->get('phone', 'default');
            $log = sprintf(_w("Notification <strong>%s</strong> sent to customer."), $n['name']);
        } elseif ($n['to'] == 'admin') {
            $to = $general['phone'];
            $log = sprintf(_w("Notification <strong>%s</strong> sent to store admin."), $n['name']);
        } else {
            $to = $n['to'];
            $log = sprintf(_w("Notification <strong>%s</strong> sent to %s."), $n['name'], $n['to']);
        }
        if (!$to) {
            return;
        }

        if ($locale) {
            $old_locale = wa()->getLocale();
            wa()->setLocale($locale);
        }

        foreach (array('shipping', 'billing') as $k) {
            $address = shopHelper::getOrderAddress($data['order']['params'], $k);
            $formatter = new waContactAddressOneLineFormatter(array('image' => false));
            $address = $formatter->format(array('data' => $address));
            $data[$k.'_address'] = $address['value'];
        }
        $order_id = $data['order']['id'];
        $data['order']['id'] = shopHelper::encodeOrderId($order_id);

        $view = wa()->getView();
        $view->assign($data);
        $text = $view->fetch('string:'.$n['text']);

        $sms = new waSMS();
        if ($sms->send($to, $text, isset($n['from']) ? $n['from'] : null)) {
            $order_log_model = new shopOrderLogModel();
            $order_log_model->add(array(
                'order_id'        => $order_id,
                'contact_id'      => null,
                'action_id'       => '',
                'text'            => '<i class="icon16 mobile"></i> '.$log,
                'before_state_id' => $data['order']['state_id'],
                'after_state_id'  => $data['order']['state_id'],
            ));
        }

        $old_locale && wa()->setLocale($old_locale);
    }

    protected static function sendHttp($n, $data)
    {

    }

    protected static function sendPushNotifications($event, $data)
    {
        if ($event != 'order.create') {
            return;
        }

        $web_push = new shopWebPushNotifications(shopWebPushNotifications::SERVER_SEND_DOMAIN);
        $web_push->send($data);

        $host_client_ids = array();
        $push_client_model = new shopPushClientModel();
        foreach ($push_client_model->getAllMobileClients() as $row) {
            $host_client_ids[$row['shop_url']][$row['client_id']] = $row['client_id'];
        }
        if (!$host_client_ids) {
            return;
        }

        $results = array();
        foreach ($host_client_ids as $shop_url => $client_ids) {
            $request_data = array(
                'app_id'             => "0b854471-089a-4850-896b-86b33c5a0198",
                'data'               => array(
                    'order_id' => $data['order']['id'],
                    'shop_url' => $shop_url,
                ),
                'include_player_ids' => array_values($client_ids),
                'contents'           => array(
                    "en" => _w('New order').' '.shopHelper::encodeOrderId($data['order']['id']),
                ),

                'ios_badgeType'  => 'Increase',
                'ios_badgeCount' => 1,
                'android_group'  => 'shop_orders',
            );

            try {
                $net = new waNet(array('format' => waNet::FORMAT_JSON));
                $net->query("https://onesignal.com/api/v1/notifications", $request_data, waNet::METHOD_POST);
                $result = $net->getResponse();
                if (!empty($result['errors'])) {
                    if (!empty($result['errors']['invalid_player_ids'])) {
                        $push_client_model->deleteById($result['errors']['invalid_player_ids']);
                    } else {
                        waLog::log('Unable to send PUSH notifications: '.wa_dump_helper($result));
                    }
                }
            } catch (Exception $ex) {
                $result = $ex->getMessage();
                waLog::log('Unable to send PUSH notifications: '.$result);
            }

            $results[] = $result;
        }

        return $results;
    }
}
