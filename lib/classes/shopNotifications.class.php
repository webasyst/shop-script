<?php
/**
 * Class for send notifications (email/sms/http).
 *
 * @todo  Combine send\sendOne, sendEmail\sendSms\sendHttp
 */
class shopNotifications
{
    /**
     * @var  shopNotificationModel
     */
    protected static $model;

    /**
     * @var  shopConfig
     */
    protected static $config;

    /**
     * Get notification model.
     *
     * @return  shopNotificationModel
     */
    protected static function getModel()
    {
        if (!self::$model) {
            self::$model = new shopNotificationModel;
        }
        return self::$model;
    }

    /**
     * Get general setting from shop config.
     *
     * @param   string  $name
     * @return  mixed
     */
    protected static function getGeneralSetting($name)
    {
        if (!self::$config) {
            self::$config = wa('shop')->getConfig();
        }
        return self::$config->getGeneralSettings($name);
    }

    /**
     * Send all notification for event.
     *
     * @param   string   $event  Event name, eg: 'order.create', 'order.delete'
     * @param   array    $data   An array of order and action data 
     * @param   string   $to     For test send or unusual situations
     * @return  void
     */
    public static function send($event, array $data, $to = '')
    {
        $notifications = self::getModel()->getByEvent($event, true);

        if (!$notifications) {
            return;
        }

        $wa = wa();

        $params_model = new shopOrderParamsModel;
        $data['order']['params'] = $params_model->get($data['order']['id'], true);
        $items_model = new shopOrderItemsModel;
        $data['order']['items'] = $items_model->getItems($data['order']['id']);

        foreach ($data['order']['items'] as $i => $val) {
            if (empty($val['file_name'])) {
                continue;
            }
 
            $val['download_link'] = $wa->getRouteUrl(
                '/frontend/myOrderDownload',
                array(
                    'id'   => $data['order']['id'],
                    'code' => $data['order']['params']['auth_code'],
                    'item' => $val['id']
                ),
                true
            );
 
            $data['order']['items'][$i] = $val;
        }

        if (!empty($data['order']['params']['shipping_id'])) {
            try {
                $data['shipping_plugin'] = shopShipping::getPlugin(
                    $data['order']['params']['shipping_plugin'],
                    $data['order']['params']['shipping_id']
                );
            } catch (Exception $e) {
            }
        }

        $source = 'backend';
        if (isset($data['order']['params']['storefront'])) {
            if (substr($data['order']['params']['storefront'], -1) == '/') {
                $source = $data['order']['params']['storefront'].'*';
            } else {
                $source = $data['order']['params']['storefront'].'/*';
            }
        }

        foreach ($notifications as $n) {
            if (!$n['source'] || $n['source'] == $source) {
                $method = 'send'.ucfirst($n['transport']);
                if (method_exists(__CLASS__, $method)) {
                    if ($to) {
                        $n['to'] = $to;
                    }
                    self::$method($n, $data);
                }
            }
        }
    }

    /**
     * Send notification.
     *
     * @param  int     $id    Notification id
     * @param  array   $data  An array of order and action data 
     * @param  string  $to    For test send or unusual situations
     * @return void
     */
    public static function sendOne($id, array $data, $to = '')
    {
        $n = self::getModel()->getOne($id);
        if (!$n) {
            return;
        }

        $wa = wa();

        $params_model = new shopOrderParamsModel;
        $data['order']['params'] = $params_model->get($data['order']['id']);
        $items_model = new shopOrderItemsModel;
        $data['order']['items'] = $items_model->getItems($data['order']['id']);

        foreach ($data['order']['items'] as $i => $val) {
            if (empty($val['file_name'])) {
                continue;
            }

            $val['download_link'] = $wa->getRouteUrl(
                '/frontend/myOrderDownload',
                array(
                    'id'   => $data['order']['id'],
                    'code' => $data['order']['params']['auth_code'],
                    'item' => $val['id']
                ),
                true
            );

            $data['order']['items'][$i] = $val;
        }

        if (!empty($data['order']['params']['shipping_id'])) {
            try {
                $data['shipping_plugin'] = shopShipping::getPlugin(
                    $data['order']['params']['shipping_plugin'],
                    $data['order']['params']['shipping_id']
                );
            } catch (Exception $e) {
            }
        }
        
        $method = 'send'.ucfirst($n['transport']);
        if (method_exists(__CLASS__, $method)) {
            if ($to) {
                $n['to'] = $to;
            }
            self::$method($n, $data);
        }
    }
    
    protected static function sendEmail(array $n, array $data)
    {
        if ($n['to'] == 'customer') {
            $to = $data['customer']->get('email', 'default');
            if (!$to) {
                return;
            }
            $to = array($to);
            $log = sprintf(_w('Notification <strong>%s</strong> sent to customer.'), $n['name']);
        } elseif ($n['to'] == 'admin') {
            $to = self::getGeneralSetting('email');
            if (!$to) {
                return;
            }
            $to = array($to);
            $log = sprintf(_w("Notification <strong>%s</strong> sent to store admin."), $n['name']);
        } else {
            $to = explode(',', $n['to']);
            $log = sprintf(_w("Notification <strong>%s</strong> sent to %s."), $n['name'], $n['to']);
        }

        $from = $n['from'] ? $n['from'] : self::getGeneralSetting('email');

        $view = wa()->getView();

        $formatter = new waContactAddressOneLineFormatter(array('image' => false));
        foreach (array('shipping', 'billing') as $k) {
            $address = shopHelper::getOrderAddress($data['order']['params'], $k);
            $address = $formatter->format(array('data' => $address));
            $view->assign($k.'_address', $address['value']);
        }

        $order_id = $data['order']['id'];
        $data['order']['id'] = shopHelper::encodeOrderId($order_id);

        $view->assign(
            'order_url', 
            wa()->getRouteUrl(
                '/frontend/myOrderByCode',
                array(
                    'id' => $order_id,
                    'code' => $data['order']['params']['auth_code']
                ),
                true
            )
        );
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
            $message->setFrom($from, self::getGeneralSetting('name'));
        }
        
        if ($message->send()) {
            $order_log_model = new shopOrderLogModel;
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

    protected static function sendSms(array $n, array $data)
    {
        if ($n['to'] == 'customer') {
            $to = $data['customer']->get('phone', 'default');
            $log = sprintf(_w('Notification <strong>%s</strong> sent to customer.'), $n['name']);
        } elseif ($n['to'] == 'admin') {
            $to = self::getGeneralSetting('phone');
            $log = sprintf(_w('Notification <strong>%s</strong> sent to store admin.'), $n['name']);
        } else {
            $to = $n['to'];
            $log = sprintf(_w('Notification <strong>%s</strong> sent to %s.'), $n['name'], $n['to']);
        }
        if (!$to) {
            return;
        }

        $from = isset($n['from']) ? $n['from'] : null;

        $view = wa()->getView();

        $formatter = new waContactAddressOneLineFormatter(array('image' => false));
        foreach (array('shipping', 'billing') as $k) {
            $address = shopHelper::getOrderAddress($data['order']['params'], $k);
            $address = $formatter->format(array('data' => $address));
            $view->assign($k.'_address', $address['value']);
        }

        $order_id = $data['order']['id'];
        $data['order']['id'] = shopHelper::encodeOrderId($order_id);

        $view->assign(
            'order_url',
            wa()->getRouteUrl(
                '/frontend/myOrderByCode',
                array(
                    'id' => $order_id,
                    'code' => $data['order']['params']['auth_code']
                ),
                true
            )
        );
        $view->assign($data);

        $text = $view->fetch('string:'.$n['text']);
 
        $sms = new waSMS;

        if ($sms->send($to, $text, $from)) {
            $order_log_model = new shopOrderLogModel;
            $order_log_model->add(array(
                'order_id'        => $order_id,
                'contact_id'      => null,
                'action_id'       => '',
                'text'            => '<i class="icon16 mobile"></i> '.$log,
                'before_state_id' => $data['order']['state_id'],
                'after_state_id'  => $data['order']['state_id'],
            ));
        }
    }

    protected static function sendHttp(array $n, array $data)
    {
    }
}
