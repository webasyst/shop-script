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

        /**
         * Run before send notification from shop
         *
         * @param string $event
         * @param array $notifications
         * @param array $data
         *
         * @event notifications_send.before
         */
        $event_params = [
            'event' => $event,
            'notifications' => $notifications,
            'data'  => &$data,
        ];
        wa('shop')->event('notifications_send.before', $event_params);

        self::prepareData($data);

        if ($notifications) {
            if (isset($data['storefront_route'])) {
                $old_route = wa()->getRouting()->getRoute();
                $old_domain = wa()->getRouting()->getDomain();
                wa()->getRouting()->setRoute($data['storefront_route'], ifset($data['storefront_domain']));
            }

            foreach ($notifications as $n) {
                if (!$n['source'] || ($n['source'] == $data['source'])) {
                    $method = 'send'.ucfirst($n['transport']);
                    if (method_exists('shopNotifications', $method)) {
                        try {
                            self::$method($n, $data);
                        } catch (Exception $ex) {
                            $error = sprintf(
                                'Unable to send %s notifications for order %s: %s',
                                ucfirst($n['transport']),
                                ifset($data['order_id'], ifset($data['id'])),
                                $ex->getMessage()
                            );
                            waLog::log($error, 'shop/notifications.log');
                        }
                    }
                }
            }

            if (isset($data['storefront_route'])) {
                wa()->getRouting()->setRoute($old_route, $old_domain);
            }
        }
        /**
         * Run after send notification from shop
         *
         * @param string $event
         * @param array $notifications
         * @param array $data
         *
         * @event notifications_send.after
         */
        $event_params = [
            'event' => $event,
            'notifications' => $notifications,
            'data'  => &$data,
        ];
        wa('shop')->event('notifications_send.after', $event_params);

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

        /**
         * Run before send one notification from shop
         *
         * @param int $id
         * @param array $notifications
         * @param array $data
         * @param string|null $to Recipient address/number.
         *
         * @event notifications_send_one.before
         */
        $event_params = [
            'id' => $id,
            'notifications' => $n,
            'data'  => &$data,
            'to' => $to,
        ];
        wa('shop')->event('notifications_send_one.before', $event_params);

        self::prepareData($data);

        $method = 'send'.ucfirst($n['transport']);
        if (method_exists('shopNotifications', $method)) {
            if (isset($data['storefront_route'])) {
                $old_route = wa()->getRouting()->getRoute();
                $old_domain = wa()->getRouting()->getDomain();
                wa()->getRouting()->setRoute($data['storefront_route'], ifset($data['storefront_domain']));
            }

            if ($to !== null) {
                $n['to'] = $to;
            }
            self::$method($n, $data);

            if (isset($data['storefront_route'])) {
                wa()->getRouting()->setRoute($old_route, $old_domain);
            }
        }

        /**
         * Run after send one notification from shop
         *
         * @param id $id
         * @param array $notifications
         * @param array $data
         * @param string|null $to Recipient address/number.
         *
         * @event notifications_send_one.after
         */
        $event_params = [
            'id' => $id,
            'notifications' => $n,
            'data'  => &$data,
            'to' => $to,
        ];

        wa('shop')->event('notifications_send_one.after', $event_params);
    }

    protected static function prepareData(&$data)
    {
        if (!is_array($data['order'])) {
            $order_model = new shopOrderModel();
            $data['order'] = $order_model->getById($data['order']);
        }

        if (empty($data['status'])) {
            $workflow = new shopWorkflow();
            $status = $workflow->getStateById($data['order']['state_id']);
            if ($status) {
                $data['status'] = $status->getName();
            } else {
                $data['status'] = $data['order']['state_id'];
            }
        }

        $params_model = new shopOrderParamsModel();
        $data['order']['params'] = $params_model->get($data['order']['id'], true);

        $items_model = new shopOrderItemsModel();
        $data['order']['items'] = $items_model->getItems($data['order']['id']);

        // last order_log entry
        if (!isset($data['action_data'])) {
            $order_log_model = new shopOrderLogModel();
            $data['action_data'] = $order_log_model->where("order_id = ? AND action_id <> ''", $data['order']['id'])->order('id DESC')->limit(1)->fetchAssoc();
            if ($data['action_data']) {
                $data['action_data']['params'] = array();
                $log_params_model = new shopOrderLogParamsModel();
                $params = $log_params_model->getByField('log_id', $data['action_data']['id'], true);
                foreach ($params as $p) {
                    $data['action_data']['params'][$p['name']] = $p['value'];
                }
            }
        }

        // Routing params to generate full URLs to products
        $source = 'backend';
        $storefront_route = null;
        $storefront_domain = null;
        $storefront_route_url = null;
        if (isset($data['order']['params']['storefront'])) {
            $storefront = $data['order']['params']['storefront'];
            if (substr($storefront, -1) === '/') {
                $source = $storefront.'*';
            } else {
                $source = $storefront.'/*';
            }

            $storefront = rtrim($storefront, '/');

            foreach (wa()->getRouting()->getByApp('shop') as $domain => $routes) {
                foreach ($routes as $r) {
                    if (!isset($r['url'])) {
                        continue;
                    }
                    $st = rtrim(rtrim($domain, '/').'/'.$r['url'], '/.*');
                    if ($st == $storefront) {
                        $storefront_route = $r;
                        $storefront_route_url = $r['url'];
                        $storefront_domain = $domain;
                        break 2;
                    }
                }
            }
        }
        $data['source'] = $source;
        $data['storefront_route'] = $storefront_route;
        $data['storefront_domain'] = $storefront_domain;

        // Products info
        $product_ids = array();
        $sku_ids = array();
        foreach ($data['order']['items'] as $i) {
            $product_ids[$i['product_id']] = 1;
            $sku_ids[$i['sku_id']] = $i['sku_id'];
        }

        $root_url = rtrim(wa()->getRootUrl(true), '/');
        $root_url_len = strlen($root_url);

        $d = $storefront_domain ? 'http://'.$storefront_domain : $root_url;

        $collection = new shopProductsCollection(
            'id/'.join(',', array_keys($product_ids)),
            array('absolute_image_url' => true)
        );
        $products = $collection->getProducts('*,image');
        foreach ($products as &$p) {
            $p['frontend_url'] = wa()->getRouteUrl('shop/frontend/product', array(
                'product_url' => $p['url'],
            ), true, $storefront_domain, $storefront_route_url);
            if (!empty($p['image'])) {
                if ($d !== $root_url) {
                    foreach (array('thumb_url', 'big_url', 'crop_url') as $url_type) {
                        $p['image'][$url_type] = $d.substr($p['image'][$url_type], $root_url_len);
                    }
                }
            }
        }
        unset($p);

        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */

        //Get actual SKU's images
        $sizes = array();
        foreach (array('thumb', 'crop', 'big') as $size) {
            $sizes[$size] = $config->getImageSize($size);
        }

        $absolute_image_url = true;
        $skus = array();
        if ($sku_ids) {
            $product_skus_model = new shopProductSkusModel();
            $sql = <<<SQL
SELECT
  s.id       sku_id,
  s.product_id,
  s.image_id id,
  i.ext,
  i.filename
FROM shop_product_skus s
  JOIN shop_product_images i ON i.id = s.image_id
WHERE
  s.image_id IS NOT NULL
  AND s.id IN (i:sku_ids)
SQL;

            $skus = $product_skus_model->query($sql, compact('sku_ids'))->fetchAll('sku_id');

            foreach ($skus as &$sku) {
                foreach ($sizes as $size_id => $size) {
                    $sku['image'][$size_id.'_url'] = shopImage::getUrl($sku, $size, $absolute_image_url);
                    if ($d !== $root_url) {
                        $sku['image'][$size_id.'_url'] = $d.substr($sku['image'][$size_id.'_url'], $root_url_len);
                    }

                }
                unset($sku);
            }
        }

        // URLs and products for order items
        foreach ($data['order']['items'] as &$i) {
            if (!empty($i['file_name'])) {
                $i['download_link'] = wa()->getRouteUrl('shop/frontend/myOrderDownload', array(
                    'id'   => $data['order']['id'],
                    'code' => $data['order']['params']['auth_code'],
                    'item' => $i['id'],
                ), true, $storefront_domain, $storefront_route_url);
            }
            if (!empty($products[$i['product_id']])) {
                $i['product'] = $products[$i['product_id']];
                if (isset($skus[$i['sku_id']]) && !empty($skus[$i['sku_id']]['image'])) {
                    $i['product']['image'] = $skus[$i['sku_id']]['image'];
                }
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

        // Shipping date and time
        $data['shipping_interval'] = null;
        list($data['shipping_date'], $data['shipping_time_start'], $data['shipping_time_end']) = shopHelper::getOrderShippingInterval($data['order']['params']);
        if ($data['shipping_date']) {
            $data['shipping_interval'] = wa_date('shortdate', $data['shipping_date']).', '.$data['shipping_time_start'].'–'.$data['shipping_time_end'];
        }

        // Signup url
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

        $data['order_url'] = wa()->getRouteUrl('/frontend/myOrderByCode', array(
            'id' => $data['order']['id'],
            'code' => ifset($data['order']['params']['auth_code'])
        ), true, $storefront_domain, $storefront_route_url);

        shopHelper::workupOrders($data['order'], true);

        $data['courier'] = null;
        if (!empty($data['order']['params']['courier_id'])) {
            $courier_model = new shopApiCourierModel();
            $data['courier'] = $courier_model->getById($data['order']['params']['courier_id']);
            if (!empty($data['courier'])) {
                foreach ($data['courier'] as $field => $value) {
                    if (strpos($field, 'api_') === 0) {
                        unset($data['courier'][$field]);
                    }
                }
                if (!empty($data['courier']['contact_id'])) {
                    $data['courier']['contact'] = new waContact($data['courier']['contact_id']);
                }
            }
        }

        // empty defaults, to avoid notices
        $empties = self::getDataEmpties();
        $data = self::arrayMergeRecursive($data, $empties);

        return $data;
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

        if (!empty($data['order']['params']['storefront'])) {
            $idna = new waIdna();
            $data['order']['params']['storefront_decoded'] = $idna->decode($data['order']['params']['storefront']);
        } else {
            $data['order']['params']['storefront_decoded'] = '';
            $data['order']['params']['storefront'] = '';
        }

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

        if (!empty($data['order']['params']['storefront'])) {
            $idna = new waIdna();
            $data['order']['params']['storefront_decode'] = $idna->decode($data['order']['params']['storefront']);
        }

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
        $push_client_model = new shopPushClientModel();

        // Figure out recipients.
        // Users are notified about new orders.
        // Couriers are notified about orders assighed to them.
        $host_client_ids = array();
        if ($event == 'order.create') {

            // Send web push notifications. This only applies to users.
            $web_push = new shopWebPushNotifications(shopWebPushNotifications::SERVER_SEND_DOMAIN);
            $web_push->send($data);

            // Fetch all users to send mobile push notifications to
            foreach ($push_client_model->getAllMobileClients() as $push_client) {
                $host_client_ids[$push_client['shop_url']][$push_client['client_id']] = $push_client['client_id'];
            }

        } else {

            // Did this order just have a new courier assigned?
            if (!empty($data['courier']['enabled']) && $data['courier']['id'] != ifset($data['order']['params']['notified_courier_id'])) {
                // Remember we've sent the notification
                $params_model = new shopOrderParamsModel();
                $params_model->setOne($data['order']['id'], 'notified_courier_id', $data['courier']['id']);

                $courier_model = new shopApiCourierModel();
                $courier = $courier_model->getById($data['courier']['id']);

                // Get this courier's client id to send notification to
                $push_client = $push_client_model->getByField('api_token', $courier['api_token'], false);
                if ($push_client) {
                    // Make sure courier's API token is still valid
                    $api_token_model = new waApiTokensModel();
                    $api_token = $api_token_model->getById($courier['api_token']);
                    if ($api_token && (!$api_token['expires'] || strtotime($api_token['expires']) > time())) {
                        // Add to recipients
                        $host_client_ids[$push_client['shop_url']][$push_client['client_id']] = $push_client['client_id'];
                    } else {
                        // Forget the client if their API token is invalid
                        $push_client_model->deleteById('api_token', $push_client['client_id']);
                    }
                }
            }

        }

        if (!$host_client_ids) {
            return;
        }

        $order = $data['order'];
        $notification_text = _w('New order').' '.shopHelper::encodeOrderId($order['id']);
        $notification_text .= ' - '.wa_currency($order['total'], $order['currency']);

        // Send to recipients, grouped by domain name they registered to
        $results = array();

        /**
         * Run before send push notification from shop
         *
         * @param string $event
         * @param array $data
         * @param string $notification_text
         * @param array $host_client_ids
         *
         * @event notifications_send_push
         */
        $event_params = [
            'event' => $event,
            'data'  => &$data,
            'notification_text' => &$notification_text,
            'host_client_ids' => $host_client_ids,
        ];
        wa('shop')->event('notifications_send_push', $event_params);

        foreach ($host_client_ids as $shop_url => $client_ids) {
            $request_data = array(
                'app_id'             => "0b854471-089a-4850-896b-86b33c5a0198",
                'data'               => array(
                    'order_id' => $order['id'],
                    'shop_url' => $shop_url,
                ),
                'include_player_ids' => array_values($client_ids),
                'contents'           => array(
                    "en" => $notification_text,
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
