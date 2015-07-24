<?php

class shopWorkflowMessageAction extends shopWorkflowAction
{
    public function getDefaultOptions()
    {
        $options = parent::getDefaultOptions();
        $options['html'] = true;
        return $options;
    }

    public function isAvailable($order)
    {
        if ($order === null) {
            return true;
        }

        if (!empty($order['contact'])) {
            $c = $order['contact'];
            if (ifset($c['email']) || ifset($c['phone'])) {
                return true;
            }
        }
        return false;
    }

    public function getOrderData($order_id)
    {
        $order_model = new shopOrderModel();
        $order = $order_model->getById($order_id);

        $order_params_model = new shopOrderParamsModel();
        $order['params'] = $order_params_model->get($order['id'], true);

        $items_model = new shopOrderItemsModel();
        $order['items'] = $items_model->getItems($order['id']);

        // Routing params to generate full URLs to products
        $source = 'backend';
        $storefront_route = null;
        $storefront_domain = null;
        if (isset($order['params']['storefront'])) {
            $storefront = $order['params']['storefront'];
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
        $order['source'] = $source;

        // Products info
        $product_ids = array();
        foreach ($order['items'] as $i) {
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
        foreach ($order['items'] as &$i) {
            if (!empty($i['file_name'])) {
                $i['download_link'] = wa()->getRouteUrl('shop/frontend/myOrderDownload', array(
                    'id' => $order['id'],
                    'code' => $order['params']['auth_code'],
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

        return $order;
    }

    public function getHTML($order_id)
    {
        $view = $this->getView();
        $order = $this->getOrderData($order_id);
        $contact = new waContact($order['contact_id']);

        $source = ifset($order['params']['storefront'], '');
        if ($source) {
            $source = trim($source, '/*').'/*';
        }

        $notification_model = new shopNotificationModel();
        $sql = "SELECT DISTINCT n.source, n.transport, np.value FROM shop_notification n
                JOIN shop_notification_params np ON n.id = np.notification_id
                WHERE np.name = 'from'";
        $rows = $notification_model->query($sql)->fetchAll();

        $transport = '';

        if ($contact->get('phone', 'default')) {
            $transport = 'sms';
            $sms_config = wa()->getConfig()->getConfigFile('sms');
            $sms_from = array();
            foreach ($sms_config as $from => $options) {
                $sms_from[$from] = $from.' ('.$options['adapter'].')';
            }

            $sms_selected = '';
            foreach ($rows as $row) {
                if ($row['transport'] == 'sms') {
                    if (!$source || $row['source'] == $source) {
                        $sms_selected = $row['value'];
                        $sms_from[$row['value']] = $row['value'];
                    }
                }
            }
            $view->assign('sms_from', $sms_from);
            $view->assign('sms_selected', $sms_selected);
            $view->assign('contact_phone', $contact->get('phone', 'default'));
        }

        if ($contact->get('email', 'default')) {
            $transport = 'email';
            $email = wa('shop')->getConfig()->getGeneralSettings('email');
            if ($email) {
                $email_from[$email] = $email;
            }
            $email_selected = '';
            foreach ($rows as $row) {
                if ($row['transport'] == 'email') {
                    if (!$source || $row['source'] == $source) {
                        $email_selected = $row['value'];
                        $email_from[$row['value']] = $row['value'];
                    }
                }
            }
            $view->assign('email_from', $email_from);
            $view->assign('email_selected', $email_selected);
            $view->assign('contact_email', $contact->get('email', 'default'));
        }

        $view->assign(array(
            'transport' => $transport,
            'message_template' => $this->getMessageTemplate($order, $contact),
        ));
        return parent::getHTML($order_id);
    }

    public function getMessageTemplate($order, $customer)
    {
        $template = ifempty($this->options['template'], '');
        if (!$template) {
            return '';
        }

        $view = $this->getView();
        $settings = wa('shop')->getConfig()->getGeneralSettings();

        $view->assign(array(
            'action' => null,
            'workhour_days' => ifset($settings['workhours']['days_from_to'], ''),
            'workhour_from' => ifset($settings['workhours']['hours_from'], ''),
            'workhour_to' => ifset($settings['workhours']['hours_to'], ''),
            'user' => wa()->getUser(),
            'customer' => $customer,
            'order' => $order,
        ));
        $result = $view->fetch('string:'.$template);
        $view->assign('action', $this);
        return $result;
    }

    public function execute($order_id = null)
    {
        $order_model = new shopOrderModel();
        $order = $order_model->getById($order_id);

        if (!$order) {
            return false;
        }

        $contact = new waContact($order['contact_id']);

        $transport = waRequest::post('transport');
        $from = waRequest::post('sender', wa('shop')->getConfig()->getGeneralSettings('email'), 'string');
        $text = waRequest::post('text');
        $success = false;
        if ($transport == 'email') {
            $message = new waMailMessage(sprintf(_w('Order %s'), shopHelper::encodeOrderId($order_id)), nl2br(htmlspecialchars($text)));
            $message->setFrom($from);
            $email = $contact->get('email', 'default');
            $message->setTo(array(
                 $email => $contact->getName()
            ));
            $text = '<i class="icon16 email float-right" title="'.htmlspecialchars($email).'"></i> '.nl2br(htmlspecialchars($text));
            $success = $message->send();
        } elseif ($transport == 'sms') {
            $sms = new waSMS();
            $phone = $contact->get('phone', 'default');
            $success = $sms->send($phone, $text, $from ? $from : null);
            $text = '<i class="icon16 mobile float-right" title="'.htmlspecialchars($phone).'"></i> '.nl2br(htmlspecialchars($text));
        }

        if ($success) {
            $log_model = new waLogModel();
            $log_model->add('order_message', $order_id);
            return array(
                'text' => $text,
            );
        } else {
            return array(
                'text' => '<span style="color:red">'._w('Error sending message to client. Message is not sent.')."</span><br><br>".$text,
            );
        }
    }

    public function getButton()
    {
        return parent::getButton('data-container="#workflow-content"');
    }
}