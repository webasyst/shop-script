<?php

class shopWorkflowSendpinAction extends shopWorkflowAction
{
    public function __construct($id, waWorkflow $workflow, $options = [])
    {
        parent::__construct($id, $workflow, $options);
        $this->options['html'] = true;
    }

    public function execute($params = null)
    {
        $order_id = $params;
        $order = $this->order_model->getOrder($order_id);

        if (!$order) {
            return false;
        }

        $from_name = '';
        $contact = new waContact($order['contact_id']);

        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();
        $general = $config->getGeneralSettings();
        if ($this->getOption('sender_name') == 'route_params') {
            $order_params = $this->order_params_model->get($order_id, true);
            if (isset($order_params['storefront'])) {
                foreach (wa()->getRouting()->getByApp('shop') as $domain => $routes) {
                    foreach ($routes as $r) {
                        if (!isset($r['url'])) {
                            continue;
                        }
                        $st = rtrim(rtrim($domain, '/').'/'.$r['url'], '/.*');
                        if ($st == rtrim($order_params['storefront'], '/.*')) {
                            if (isset($r['_name'])) {
                                $from_name = $r['_name'];
                            }
                            break 2;
                        }
                    }
                }
            }
        } else {
            $from_name = $general['name'];
        }

        $success = false;
        $transport = waRequest::post('transport');
        $from = waRequest::post('sender', $transport == 'email' ? $this->getConfig()->getGeneralSettings('email') : null, 'string');

        $notification = [];
        $notifications = (new shopNotificationModel())->getByEvent('order.'.$this->getId(), true);
        foreach ($notifications as $_notification) {
            if ($_notification['transport'] == $transport) {
                $notification = $_notification;
                break;
            }
        }

        if (empty($notification)) {
            return [
                'text' => '<span style="color:red">'._w('Sending failed. No templates exist.').'</span>',
            ];
        }
        $view = wa()->getView();
        if ($transport == 'email') {
            $view->assign([
                'order'       => $order,
                'customer'    => $contact,
                'status'      => $this->getWorkflow()->getStateById($order['state_id'])->getName(),
                'order_url'   => '',
                'action_data' => []
            ]);
            $subject = $view->fetch('string:'.ifset($notification, 'subject', sprintf(_w('Order %s'), shopHelper::encodeOrderId($order_id))));
            $body = $view->fetch('string:'.ifset($notification, 'body', ''));

            $message = new waMailMessage($subject, $body);
            $message->setFrom($from, $from_name);
            $email = $contact->get('email', 'default');
            $message->setTo(array(
                $email => $contact->getName()
            ));
            $success = $message->send();
        } elseif ($transport == 'sms') {
            $view->assign([
                'order'    => $order,
                'customer' => $contact,
            ]);
            $body = $view->fetch('string:'.ifset($notification, 'text', ''));
            $sms = new waSMS();
            $phone = $contact->get('phone', 'default');
            $success = $sms->send($phone, $body, $from ?: null);
        }

        if ($success) {
            $this->waLog('order_message', $order_id);
            return [
                'text' => sprintf(_w('PIN was sent to %s'), $transport)
            ];
        } else {
            return [
                'text' => '<span style="color:red">'._w('Error sending message to client. Message is not sent.').'</span>',
            ];
        }
    }

    public function getButton()
    {
        return parent::getButton('data-container="#workflow-content"');
    }

    public function isAvailable($order)
    {
        if ($order === null) {
            return true;
        }
        if (!($order instanceof shopOrder) && (empty($order['contact']) || !($order['contact'] instanceof waContact))) {
            $order = new shopOrder($order['id']);
        }

        if (!empty($order['contact'])) {
            $c = $order['contact'];
            if (ifset($c, 'email', null) || ifset($c, 'phone', null)) {
                return true;
            }
        }

        return false;
    }

    public function getHTML($order_id)
    {
        $view = $this->getView();
        $order = $this->order_model->getById($order_id);
        $order_params = $this->order_params_model->get($order_id, true);
        $contact = new waContact($order['contact_id']);

        $source = ifset($order_params, 'storefront', '');
        if ($source && $source != 'backend' && $source != 'all_sources') {
            $source = trim($source, '/*').'/*';
        }

        $notification_model = new shopNotificationModel();
        $rows = $notification_model->getAllTransportSources();

        $transport = '';
        if ($phone = $contact->get('phone', 'default')) {
            $sms_from = [];
            $transport = 'sms';
            $sms_selected = '';
            if (waSMS::adapterExists()) {
                $sms_config = wa()->getConfig()->getConfigFile('sms');
                foreach ($sms_config as $from => $options) {
                    $sms_from[$from] = $from.' ('.$options['adapter'].')';
                }
            }

            foreach ($rows as $row) {
                if ($row['transport'] == 'sms') {
                    if (!$source || $row['source'] == $source) {
                        $sms_selected = $row['value'];
                        $sms_from[$row['value']] = $row['value'];
                    }
                }
            }
            $view->assign([
                'sms_from'      => $sms_from,
                'sms_selected'  => $sms_selected,
                'contact_phone' => $phone
            ]);
        }

        if ($e_mail = $contact->get('email', 'default')) {
            $email_from = [];
            $transport = 'email';
            $email_selected = '';
            $email = $this->getConfig()->getGeneralSettings('email');
            if ($email) {
                $email_from[$email] = $email;
            }
            foreach ($rows as $row) {
                if ($row['transport'] == 'email') {
                    if (!$source || $row['source'] == $source) {
                        $email_selected = $row['value'];
                        $email_from[$row['value']] = $row['value'];
                    }
                }
            }
            $view->assign([
                'email_from'     => $email_from,
                'email_selected' => $email_selected,
                'contact_email'  => $e_mail
            ]);
        }

        $view->assign([
            'transport' => $transport,
        ]);

        return parent::getHTML($order_id);
    }
}
