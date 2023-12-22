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
        // compatibility with old versions
        foreach ($notifications as $id => $notification) {
            $notifications[$id]['source'] = isset($data['order']['params']['storefront']) ? $data['order']['params']['storefront'] : null;
        }

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
            'notifications' => &$notifications,
            'data'  => &$data,
        ];
        wa('shop')->event('notifications_send.before', $event_params);

        self::prepareData($data);

        $send_results = array();

        if ($notifications) {
            if (isset($data['storefront_route'])) {
                $old_route = wa()->getRouting()->getRoute();
                $old_domain = wa()->getRouting()->getDomain();
                wa()->getRouting()->setRoute($data['storefront_route'], ifset($data['storefront_domain']));
            }

            foreach ($notifications as $n) {
                $send_res = array(
                    'status' => false,
                    'log_id' => null,
                    'transport' => null
                );
                self::addFrontendUrls($data, $n);
                if (!isset($n['sources'])
                    || in_array('all_sources', $n['sources']) !== false
                    || in_array($data['source'], $n['sources']) !== false
                ) {
                    $method = 'send'.ucfirst($n['transport']);
                    if (method_exists('shopNotifications', $method)) {
                        try {
                            $send_res = self::$method($n, $data);
                            $send_res['transport'] = $n['transport'];
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
                $send_results[$n['id']] = $send_res;
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
         * @param array $send_results - indexed by notification id array of result of sending
         * @param array $send_results[<notification.id>]
         * @param bool  $send_results[<notification.id>]['status']
         * @param int|null $send_results[<notification.id>]['log_id'] shop_order_log.id
         * @param string   $send_results[<notification.id>]['transport'] 'sms' | 'email'
         *
         * @event notifications_send.after
         */
        $event_params = [
            'event' => $event,
            'notifications' => &$notifications,
            'data'  => &$data,
            'send_results' => $send_results
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
            'notifications' => &$n,
            'data'  => &$data,
            'to' => &$to,
        ];
        wa('shop')->event('notifications_send_one.before', $event_params);

        self::prepareData($data);

        $method = 'send'.ucfirst($n['transport']);
        if (method_exists('shopNotifications', $method)) {
            self::addFrontendUrls($data, $n);
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
            'notifications' => &$n,
            'data'  => &$data,
            'to' => &$to,
        ];

        wa('shop')->event('notifications_send_one.after', $event_params);
    }

    protected static function prepareData(&$data)
    {
        if (!is_array($data['order'])) {
            $order_model = new shopOrderModel();
            $data['order'] = $order_model->getById($data['order']);
        }
        $data['order']['tax'] = shopOrderAction::calculateNotIncludedTax($data['order']);

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

        $storefront_params = self::getStorefrontParams(ifset($data['order']['params']['storefront']));
        $data = array_merge($data, $storefront_params);

        // Products info
        $product_ids = array();
        $sku_ids = array();
        foreach ($data['order']['items'] as $i) {
            $product_ids[$i['product_id']] = 1;
            $sku_ids[$i['sku_id']] = $i['sku_id'];
        }

        $root_url = rtrim(wa()->getRootUrl(true), '/');
        $root_url_len = strlen($root_url);

        $d = $data['storefront_domain'] ? 'http://'.$data['storefront_domain'] : $root_url;

        $collection = new shopProductsCollection(
            'id/'.join(',', array_keys($product_ids)),
            array(
                'absolute_image_url' => true,
                'defrac_counts' => true,
            )
        );
        $products = $collection->getProducts('*,image');
        foreach ($products as &$p) {
            if (!empty($p['image'])) {
                if ($d !== $root_url) {
                    foreach (array('thumb_url', 'big_url', 'crop_url') as $url_type) {
                        $p['image'][$url_type] = $d.substr($p['image'][$url_type], $root_url_len);
                    }
                } elseif (wa()->getEnv() === 'cli' && isset($p['frontend_url'])) {
                    foreach (array('thumb_url', 'big_url', 'crop_url') as $url_type) {
                        $p['image'][$url_type] = parse_url($p['frontend_url'], PHP_URL_SCHEME).'://'
                            .parse_url($p['frontend_url'], PHP_URL_HOST)
                            .parse_url($p['image'][$url_type],  PHP_URL_PATH);
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
            $i['quantity'] = shopFrac::discardZeros($i['quantity']);
            if (!empty($i['file_name'])) {
                $i['download_link'] = wa()->getRouteUrl('shop/frontend/myOrderDownload', array(
                    'id'   => $data['order']['id'],
                    'code' => $data['order']['params']['auth_code'],
                    'item' => $i['id'],
                ), true, ifset($storefront_domain), ifset($storefront_route_url)); // :TODO init vars
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
        $data['shipping_plugin'] = null;
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

    /**
     * @param $storefront
     * @return array
     * @throws waException
     */
    protected static function getStorefrontParams($storefront)
    {
        $source = 'backend';
        $storefront_route = null;
        $storefront_domain = null;
        if (isset($storefront)) {
            if (substr($storefront, -1) === '/') {
                $source = $storefront.'*';
            } elseif (substr($storefront, -2) !== '/*') {
                $source = $storefront.'/*';
            }

            $storefront = rtrim($storefront, '/*');

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

        return [
            'source' => $source,
            'storefront_route' => $storefront_route,
            'storefront_domain' => $storefront_domain
        ];
    }

    /**
     * @param $data
     * @param $notification
     * @depends prepareData
     * @throws waException
     */
    protected static function addFrontendUrls(&$data, $notification)
    {
        $storefront_domain = $data['storefront_domain'];
        $storefront_route_url = isset($data['storefront_route']['url']) ? $data['storefront_route']['url'] : null;
        if (!isset($storefront_route_url) && !empty($notification['sources'])) {
            foreach ($notification['sources'] as $source) {
                if ($source != 'backend') {
                    $storefront_params = self::getStorefrontParams($source);
                    $storefront_domain = $storefront_params['storefront_domain'];
                    $storefront_route_url = ifset($storefront_params['storefront_route']['url']);
                    break;
                }
            }
        }
        $data['order_url'] = wa()->getRouteUrl('/frontend/myOrderByCode', array(
            'id' => $data['order']['id'],
            'code' => ifset($data['order']['params']['auth_code'])
        ), true, $storefront_domain, $storefront_route_url);
        foreach ($data['order']['items'] as &$i) {
            if (!empty($i['file_name'])) {
                $i['download_link'] = wa()->getRouteUrl('shop/frontend/myOrderDownload', array(
                    'id'   => $data['order']['id'],
                    'code' => $data['order']['params']['auth_code'],
                    'item' => $i['id'],
                ), true, $storefront_domain, $storefront_route_url);
            }
            if (isset($i['product']['url'])) {
                $i['product']['frontend_url'] = wa()->getRouteUrl('shop/frontend/product', array(
                    'product_url' => $i['product']['url'],
                ), true, $storefront_domain, $storefront_route_url);
            }
        }
        unset($i);
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

    /**
     * @param $n
     * @param $data
     * @throws waException
     * @return array
     *  - bool 'status' bool
     *  - int|null 'log_id' - shop_order_log.id
     */
    protected static function sendEmail($n, $data)
    {
        $fail_result = array(
            'status' => false,
            'log_id' => null
        );

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
        $customer = ifset($data['customer']);
        $locale = null;
        $old_locale = null;
        if ($n['to'] == 'customer') {
            $locale = $customer->getLocale();
            $email = $customer->get('email', 'default');
            if (!$email) {
                return $fail_result;
            }
            $to = array($email);
            $log = sprintf(_w("Notification <strong>%s</strong> sent to customer."), $n['name']);
        } elseif ($n['to'] == 'admin') {
            if (!$general['email']) {
                return $fail_result;
            }
            $to = array($general['email']);
            $log = sprintf(_w("Notification <strong>%s</strong> sent to store admin."), $n['name']);
        } else {
            $to = explode(',', $n['to']);
            $log = sprintf(_w("Notification <strong>%s</strong> sent to %s."), $n['name'], $n['to']);
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
            $data['order']['params']['storefront_decoded'] = $idna->decode($data['order']['params']['storefront']);
        } else {
            $data['order']['params']['storefront_decoded'] = '';
            $data['order']['params']['storefront'] = '';
        }

        $view = wa()->getView();
        $view->assign($data);
        $subject = $view->fetch('string:'.$n['subject']);
        $body = $view->fetch('string:'.$n['body']);

        // Uncomment for test on the frontend
        /*header('Content-Type: application/json;');
        exit(json_encode([
            "status" => "test",
            "html" => $body
        ]));*/

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
                    $order_storefront = $data['order']['params']['storefront'];
                    if ($routes = wa()->getRouting()->getByApp('shop')) {
                        foreach ($routes as $domain => $domain_routes) {
                            foreach ($domain_routes as $route) {
                                $route_storefront = $domain;
                                $route_url = waRouting::clearUrl($route['url']);
                                if ($route_url) {
                                    $route_storefront .= '/'.$route_url;
                                }
                                if (trim($route_storefront, '/') == trim($order_storefront, '/')) {
                                    if (isset($route['_name'])) {
                                        $from_name = $route['_name'];
                                    } else {
                                        $app = wa()->getAppInfo('shop');
                                        $from_name = $app['name'];
                                    }
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
            $message->setFrom($from, $from_name);
        }

        if ($old_locale) {
            wa()->setLocale($old_locale);
        }

        if (!$message->send()) {
            return $fail_result;
        }

        $order_log_model = new shopOrderLogModel();
        $log_id = $order_log_model->add(array(
            'order_id'        => $order_id,
            'contact_id'      => null,
            'action_id'       => '',
            'text'            => '<i class="icon16 email fas fa-envelope text-gray custom-mr-4"></i> '.$log,
            'before_state_id' => $data['order']['state_id'],
            'after_state_id'  => $data['order']['state_id'],
        ));

        return array(
            'status' => true,
            'log_id' => $log_id
        );
    }

    /**
     * @param $n
     * @param $data
     * @throws waException
     * @return array
     *  - bool 'status' bool
     *  - int|null 'log_id' - shop_order_log.id
     */
    protected static function sendSms($n, $data)
    {
        $fail_result = array(
            'status' => false,
            'log_id' => null
        );

        $general = wa('shop')->getConfig()->getGeneralSettings();
        /**
         * @var waContact $customer
         */
        $customer = ifset($data['customer']);
        $locale = $old_locale = null;
        if ($n['to'] == 'customer') {
            if (!$customer) {
                return $fail_result;
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
            return $fail_result;
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

        $status = $sms->send($to, $text, isset($n['from']) ? $n['from'] : null);

        $old_locale && wa()->setLocale($old_locale);

        if (!$status) {
            return $fail_result;
        }

        $order_log_model = new shopOrderLogModel();
        $log_id = $order_log_model->add(array(
            'order_id'        => $order_id,
            'contact_id'      => null,
            'action_id'       => '',
            'text'            => '<i class="icon16 mobile fas fa-mobile-alt text-gray custom-mr-4"></i> '.$log,
            'before_state_id' => $data['order']['state_id'],
            'after_state_id'  => $data['order']['state_id'],
        ));

        return array(
            'status' => true,
            'log_id' => $log_id
        );

    }

    /**
     * @param $n
     * @param $data
     * @return array
     * @throws waException
     * @since Shop-Script 8.15.0
     */
    protected static function sendHttp($n, $data)
    {
        $fail_result = [
            'status' => false,
            'log_id' => null
        ];
        $webhook_url = ifset($n, 'to', false);
        if (!$webhook_url) {
            return $fail_result;
        }

        //GET params
        if (!empty($n['get'])) {
            $n['get'] = self::prepareHttpParam($data, $n['get']);
            if (!empty($n['get']) && strpos($webhook_url, '?') === false) {
                $webhook_url.= '?';
                $webhook_url.= http_build_query($n['get']);
            }
        }

        //POST params
        if (!empty($n['post'])) {
            $n['post'] = self::prepareHttpParam($data, $n['post']);
        }

        $options = [
            'request_format' => waNet::FORMAT_JSON,
            'format' => waNet::FORMAT_JSON
        ];

        if ($n['format'] === 'raw') {
            $options = [
                'request_format' => waNet::FORMAT_RAW,
                'format' => waNet::FORMAT_RAW
            ];
        }

        $net = new waNet($options);
        try {
            $net->query($webhook_url, $n['post'], empty($n['post'])?waNet::METHOD_GET:waNet::METHOD_POST);
        } catch (waException $e) {
            waLog::dump($net->getResponse(),$e->getMessage(), 'shop/webhook.log');
            return $fail_result;
        }

        $order_id = $data['order']['id'];
        $log = sprintf(_w('HTTP request <strong>%s</strong> has been sent.'), $n['name']);
        $order_log_model = new shopOrderLogModel();
        $log_id = $order_log_model->add(
            [
                'order_id' => $order_id,
                'contact_id' => null,
                'action_id' => '',
                'text' => '<i class="icon16 globe fas fa-globe text-gray custom-mr-4"></i> ' . $log,
                'before_state_id' => $data['order']['state_id'],
                'after_state_id' => $data['order']['state_id'],
            ]
        );
        return [
            'status' => true,
            'log_id' => $log_id
        ];
    }

    /**
     * @param $data
     * @param $params
     * @return false|string[]
     * @throws waException
     */
    private static function prepareHttpParam($data, $params)
    {
        $view = wa()->getView();
        $view->assign($data);
        $params = self::parseParams($params);
        foreach ($params as &$param) {
            //Skip text e.g. order=order
            if (strpos($param, '{') === false) {
                continue;
            }
            //Check for existing keys in order data
            $preset_key = trim($param,'{$}');
            if (array_key_exists($preset_key, $data)) {
                $param = $data[$preset_key];
                continue;
            }
            try {
                $param = $view->fetch('string:' . $param);
            } catch (Exception $e) {
                waLog::dump($e->getMessage(), 'shop/webhook.error.log');
                continue;
            }
            //Check if param is encoded array like order={$order.items|json_encode}
            try {
                $test_array = @json_decode($param, true);
                if (is_array($test_array)) {
                    $param = $test_array;
                }
            } catch (Exception $e) {
                continue;
            }
        }
        return $params;
    }

    /**
     * @param $params
     * @return false|string[]
     */
    protected static function parseParams($params)
    {
        $result = [];
        if ($params) {
            $params = explode("\n", $params);
            foreach ($params as $param) {
                $param = explode('=', trim($param), 2);
                if (count($param) === 2) { //?
                    $result[$param[0]] = $param[1];
                }
            }
        }
        return $result;
    }

    protected static function sendPushNotifications($event, $data)
    {
        $push_client_model = new shopPushClientModel();

        // Figure out recipients.
        // Users are notified about new orders.
        // Couriers are notified about orders assigned to them.
        $host_client_ids = array();
        if ($event == 'order.create') {

            // Send web push notifications. This only applies to users.
            $web_push = new shopWebPushNotifications();
            $web_push->send($data);

            // Users to send mobile push notifications to
            // do not include users without access to new orders
            $push_clients = $push_client_model->getAllMobileClients();
            $push_clients = self::filterOutByRights($push_clients);
            foreach ($push_clients as $push_client) {
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
                        $push_client_model->deleteByField('client_id', $push_client['client_id']);
                    }
                }
            } elseif ($data['order']['courier_contact_id'] != ifset($data['order']['params']['notified_courier_contact_id'])) {
                $params_model = new shopOrderParamsModel();
                $params_model->setOne($data['order']['id'], 'notified_courier_contact_id', $data['order']['courier_contact_id']);

                $contact_model = new waContactModel();
                $is_user = $contact_model->select('is_user')->where('is_user = 1 AND id = ?', $data['order']['courier_contact_id'])->fetchField('is_user');
                $contact_rights_model = new waContactRightsModel();
                $can_access = $contact_rights_model->get($data['order']['courier_contact_id'], 'shop', 'backend');

                if ($is_user && $can_access) {
                    $notification_text = sprintf_wp('You have been assigned as courier for order %s.', shopHelper::encodeOrderId($data['order']['id']));
                    $shop_orders_app_url = wa()->getRootUrl(true) . wa()->getConfig()->getBackendUrl() . '/shop?action=orders';
                    $workflow = new shopWorkflow();
                    $push_data = array(
                        'title' => $notification_text,
                        'message' => _w('Current order status') . ' ' . $workflow->getStateById($data['order']['state_id'])->getName(),
                        'url' => $shop_orders_app_url . '#/orders/state_id=new|processing|auth|paid&id=' . $data['order']['id'] . '/',
                    );

                    // Send web push notification
                    $web_push = new shopWebPushNotifications();
                    $web_push->sendByContactId($data, $notification_text, [$data['order']['courier_contact_id']], $push_data);
                }

                $push_clients = $push_client_model->getByField([
                    'contact_id' => $data['order']['courier_contact_id'],
                    'type' => ['', 'mobile']
                ], true);
                if ($push_clients) {

                    $delete_push_clients = [];

                    // Make sure client's API token is still valid
                    $api_token_model = new waApiTokensModel();
                    $api_tokens = $api_token_model->getById(array_column($push_clients, 'api_token'));

                    foreach ($push_clients as $push_client) {
                        $api_token = ifset($api_tokens, $push_client['api_token'], null);
                        if ($is_user && $can_access && $api_token && (!$api_token['expires'] || strtotime($api_token['expires']) > time())) {
                            // Add to recipients
                            $host_client_ids[$push_client['shop_url']][$push_client['client_id']] = $push_client['client_id'];
                        } else {
                            // Forget the client if their API token is invalid
                            $delete_push_clients[$push_client['client_id']] = $push_client['client_id'];
                        }
                    }
                    if ($delete_push_clients) {
                        $push_client_model->deleteByField('client_id', array_values($delete_push_clients));
                    }
                }
            }

        }

        if (!$host_client_ids) {
            return;
        }

        $order = $data['order'];
        $notification_text = _w('New order').' '.shopHelper::encodeOrderId($order['id']);
        $notification_text .= ' — '.wa_currency($order['total'], $order['currency']);

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
            'event'             => $event,
            'data'              => &$data,
            'notification_text' => &$notification_text,
            'host_client_ids'   => $host_client_ids,
        ];
        wa('shop')->event('notifications_send_push', $event_params);

        $request_url = 'https://www.shop-script.com/push/api/v1/push.send/';

        /**
         * @var $config shopConfig
         */
        $config = wa('shop')->getConfig();
        if ($config->getOption('custom_push_sender')) {
            $request_url = $config->getOption('custom_push_sender');
        }

        $d = waRequest::server('HTTP_HOST');
        if (empty($d)) {
            $asm = new waAppSettingsModel();
            $root_url = wa()->getRootUrl(true);
            $root_url = $asm->get('webasyst', 'url', $root_url);
            $root_parsed_url = parse_url($root_url);
            if (!empty($root_parsed_url['host'])) {
                $d = $root_parsed_url['host'];
            }
        }

        $wa_installer_apps = 'wa-installer/lib/classes/wainstallerapps.class.php';
        if (!class_exists('waInstallerApps') && file_exists(wa()->getConfig()->getRootPath() .'/'. $wa_installer_apps)) {
            $autoload = waAutoload::getInstance();
            $autoload->add('waInstallerApps', $wa_installer_apps);
        }
        if (class_exists('waInstallerApps')) {
            $current_app = wa()->getApp();
            if (wa()->appExists('installer')) {
                wa('installer', 1);
            }

            $wa_installer = new waInstallerApps();
            $h = $wa_installer->getHash();
            wa($current_app, 1);
        }

        $a = 'shop';
        $v = wa($a)->getVersion($a);

        foreach ($host_client_ids as $shop_url => $client_ids) {
            $include_player_ids = array_values($client_ids);
            $request_data = [
                'push' => [
                    'message'            => $notification_text,
                    'data'               => [
                        'order_id' => $order['id'],
                        'shop_url' => $shop_url
                    ],
                    'include_player_ids' => $include_player_ids,
                ]
            ];

            $request_data['d'] = $d;
            if (!empty($h)) {
                $request_data['h'] = $h;
            }

            $request_data['s'] = $a;
            $request_data['v'] = $v;

            try {
                $net = new waNet(['timeout' => 10]);
                $net->query($request_url, $request_data, waNet::METHOD_POST);
                $result = $net->getResponse();
                $result = json_decode($result, true);

                if (!empty($result['errors'])) {
                    if (!empty($result['errors']['invalid_player_ids'])) {
                        $push_client_model->deleteById($result['errors']['invalid_player_ids']);
                    } elseif (!empty($result['errors'][0])
                        && $result['errors'][0] == 'All included players are not subscribed'
                    ) {
                        $push_client_model->deleteById($include_player_ids);
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

    public static function filterOutByRights(array $push_clients)
    {
        $contact_ids = [];
        foreach ($push_clients as $push_client) {
            if (!empty($push_client['is_courier'])) {
                continue;
            }
            $contact_ids[$push_client['contact_id']] = $push_client['contact_id'];
        }
        if (!$contact_ids) {
            return [];
        }

        $contact_rights = (new waContactRightsModel())->getByIds(array_values($contact_ids), 'shop', 'orders');
        $contact_rights = array_filter($contact_rights, function($v) {
            return $v > shopRightConfig::RIGHT_ORDERS_COURIER;
        });

        return array_filter($push_clients, function($push_client) use ($contact_rights) {
            return empty($push_client['is_courier']) && isset($contact_rights[$push_client['contact_id']]);
        });
    }
}
