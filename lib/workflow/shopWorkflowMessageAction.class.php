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
        if (!empty($order['contact'])) {
            $c = $order['contact'];
            if (ifset($c['email']) || ifset($c['phone'])) {
                return true;
            }
        }
        return false;
    }

    public function getHTML($order_id)
    {
        $view = $this->getView();

        $order_model = new shopOrderModel();
        $order = $order_model->getById($order_id);

        $contact = new waContact($order['contact_id']);

        $order_params_model = new shopOrderParamsModel();
        $source = $order_params_model->getOne($order_id, 'storefront');
        if ($source && substr($source, -1) !== '*') {
            $source .= '*';
        }

        $notification_model = new shopNotificationModel();
        $sql = "SELECT DISTINCT n.source, n.transport, np.value FROM shop_notification n
                JOIN shop_notification_params np ON n.id = np.notification_id
                WHERE np.name = 'from'";
        $rows = $notification_model->query($sql)->fetchAll();

        $transport = '';

        if ($contact->get('phone', 'default')) {
            $sms_config = wa()->getConfig()->getConfigFile('sms');
            $sms_from = array();
            foreach ($sms_config as $from => $options) {
                $sms_from[$from] = $from.' ('.$options['adapter'].')';
            }

            $sms_selected = '';
            foreach ($rows as $row) {
                if ($row['transport'] == 'sms') {
                    if ($row['source'] == $source) {
                        $sms_selected = $row['value'];
                        $sms_from[$row['value']] = $row['value'];
                    }
                }
            }
            $view->assign('sms_selected', $sms_selected);
            $view->assign('sms_from', $sms_from);
            $transport = 'sms';
            $view->assign('contact_phone', $contact->get('phone', 'default'));
        }

        if ($contact->get('email', 'default')) {
            $email = wa('shop')->getConfig()->getGeneralSettings('email');
            if ($email) {
                $email_from[$email] = $email;
            }
            $email_selected = '';
            foreach ($rows as $row) {
                if ($row['transport'] == 'email') {
                    if ($row['source'] == $source) {
                        $email_selected = $row['value'];
                        $email_from[$row['value']] = $row['value'];
                    }
                }
            }
            $view->assign('email_selected', $email_selected);
            $view->assign('email_from', $email_from);
            $transport = 'email';
            $view->assign('contact_email', $contact->get('email', 'default'));
        }

        $view->assign('transport', $transport);
        return parent::getHTML($order_id);
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
        $from = waRequest::post('from');
        $text = waRequest::post('text');
        if ($transport == 'email') {
            $message = new waMailMessage(sprintf(_w('Order %s'), shopHelper::encodeOrderId($order_id)), nl2br(htmlspecialchars($text)));
            $message->setFrom($from);
            $email = $contact->get('email', 'default');
            $message->setTo(array(
                 $email => $contact->getName()
            ));
            $text = '<i class="icon16 email float-right" title="'.htmlspecialchars($email).'"></i> '.nl2br(htmlspecialchars($text));
            $message->send();
        } elseif ($transport == 'sms') {
            $sms = new waSMS();
            $phone = $contact->get('phone', 'default');
            $sms->send($phone, $text, $from ? $from : null);
            $text = '<i class="icon16 mobile float-right" title="'.htmlspecialchars($phone).'"></i> '.nl2br(htmlspecialchars($text));
        }

        $log_model = new waLogModel();
        $log_model->add('order_message', $order_id);

        return array(
            'text' => $text
        );
    }

    public function getButton()
    {
        return parent::getButton('data-container="#workflow-content"');
    }
}