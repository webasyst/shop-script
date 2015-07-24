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
        if (!$notifications) {
            return;
        }

        self::prepareData($data);

        foreach ($notifications as $n) {
            if (!$n['source'] || ($n['source'] == $data['source'])) {
                $method = 'send'.ucfirst($n['transport']);
                if (method_exists('shopNotifications', $method)) {
                    self::$method($n, $data);
                }
            }
        }
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

            foreach(wa()->getRouting()->getByApp('shop') as $domain => $routes) {
                foreach($routes as $r) {
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
        foreach($products as &$p) {
            $p['frontend_url'] = wa()->getRouteUrl('shop/frontend/product', array(
                'product_url' => $p['url'],
            ), true, $storefront_domain, $storefront_route['url']);
            if (!empty($p['image'])) {
                $p['image']['thumb_url'] = $d.$p['image']['thumb_url'];
                $p['image']['big_url'] = $d.$p['image']['big_url'];
            }
        }
        unset($p);

        // URLs and products for order items
        foreach ($data['order']['items'] as &$i) {
            if (!empty($i['file_name'])) {
                $i['download_link'] = wa()->getRouteUrl('shop/frontend/myOrderDownload', array(
                    'id' => $data['order']['id'],
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
                $data['shipping_plugin'] = shopShipping::getPlugin($data['order']['params']['shipping_plugin'], $data['order']['params']['shipping_id']);
            } catch (waException $e) {}
        }
    }

    protected static function sendEmail($n, $data)
    {
        $general = wa('shop')->getConfig()->getGeneralSettings();
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

        $view = wa()->getView();

        foreach (array('shipping', 'billing') as $k) {
            $address = shopHelper::getOrderAddress($data['order']['params'], $k);
            $formatter = new waContactAddressOneLineFormatter(array('image' => false));
            $address = $formatter->format(array('data' => $address));
            $view->assign($k.'_address', $address['value']);
        }
        $order_id = $data['order']['id'];
        $data['order']['id'] = shopHelper::encodeOrderId($order_id);
        $view->assign('order_url', wa()->getRouteUrl('/frontend/myOrderByCode', array('id' => $order_id, 'code' => ifset($data['order']['params']['auth_code'])), true));
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
            $message->setFrom($from, $general['name']);
        }

        if ($message->send()) {
            $order_log_model = new shopOrderLogModel();
            $order_log_model->add(array(
                'order_id' => $order_id,
                'contact_id' => null,
                'action_id' => '',
                'text' => '<i class="icon16 email"></i> '.$log,
                'before_state_id' => $data['order']['state_id'],
                'after_state_id' => $data['order']['state_id'],
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

        $view = wa()->getView();

        foreach (array('shipping', 'billing') as $k) {
            $address = shopHelper::getOrderAddress($data['order']['params'], $k);
            $formatter = new waContactAddressOneLineFormatter(array('image' => false));
            $address = $formatter->format(array('data' => $address));
            $view->assign($k.'_address', $address['value']);
        }
        $order_id = $data['order']['id'];
        $data['order']['id'] = shopHelper::encodeOrderId($order_id);
        $view->assign('order_url', wa()->getRouteUrl('/frontend/myOrderByCode', array('id' => $order_id, 'code' => $data['order']['params']['auth_code']), true));
        $view->assign($data);
        $text = $view->fetch('string:'.$n['text']);

        $sms = new waSMS();
        if ($sms->send($to, $text, isset($n['from']) ? $n['from'] : null)) {
            $order_log_model = new shopOrderLogModel();
            $order_log_model->add(array(
                'order_id' => $order_id,
                'contact_id' => null,
                'action_id' => '',
                'text' => '<i class="icon16 mobile"></i> '.$log,
                'before_state_id' => $data['order']['state_id'],
                'after_state_id' => $data['order']['state_id'],
            ));
        }

        $old_locale && wa()->setLocale($old_locale);
    }

    protected static function sendHttp($n, $data)
    {

    }
}