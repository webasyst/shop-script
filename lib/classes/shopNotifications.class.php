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

    public static function send($event, $data)
    {
        $notifications = self::getModel()->getByEvent($event);
        if ($notifications) {
            $params_model = new shopOrderParamsModel();
            $data['order']['params'] = $params_model->get($data['order']['id']);
            $items_model = new shopOrderItemsModel();
            $data['order']['items'] = $items_model->getItems($data['order']['id']);
            foreach ($data['order']['items'] as &$i) {
                if (!empty($i['file_name'])) {
                    $i['download_link'] = wa()->getRouteUrl('/frontend/myOrderDownload',
                        array('id' => $data['order']['id'], 'code' => $data['order']['params']['auth_code'], 'item' => $i['id']), true);
                }
            }
            unset($i);
            if (!empty($data['order']['params']['shipping_id'])) {
                try {
                    $data['shipping_plugin'] = shopShipping::getPlugin($data['order']['params']['shipping_plugin'], $data['order']['params']['shipping_id']);
                } catch (waException $e) {}
            }
        }
        foreach ($notifications as $n) {
            $method = 'send'.ucfirst($n['transport']);
            if (method_exists('shopNotifications', $method)) {
                self::$method($n, $data);
            }
        }
    }

    protected static function sendEmail($n, $data)
    {
        $general = wa('shop')->getConfig()->getGeneralSettings();
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
        $view->assign('order_url', wa()->getRouteUrl('/frontend/myOrderByCode', array('id' => $order_id, 'code' => $data['order']['params']['auth_code']), true));
        $view->assign($data);
        $subject = $view->fetch('string:'.$n['subject']);
        $body = $view->fetch('string:'.$n['body']);

        $message = new waMailMessage($subject, $body);
        $message->setTo($to);
        if ($general['email']) {
            $message->setFrom($general['email'], $general['name']);
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
        $customer = $data['customer'];
        if ($n['to'] == 'customer') {
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
        $sms->setFrom(isset($n['from']) ? $n['from'] : null);
        if ($sms->send($to, $text)) {
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
    }

    protected static function sendHttp($n, $data)
    {

    }
}