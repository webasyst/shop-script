<?php

/**
 * Cron job to send delayed follow-up emails to customers after successfull orders.
 * Expected to run at least once an hour.
 */
class shopFollowupCli extends waCliController
{
    public function execute()
    {
        $fm = new shopFollowupModel();
        $opm = new shopOrderParamsModel();
        $asm = new waAppSettingsModel();
        $cm = new shopCustomerModel();
        $om = new shopOrderModel();

        $asm->set('shop', 'last_followup_cli', time());

        $view = wa()->getView();
        $empty_customer = $cm->getEmptyRow();

        foreach($fm->getAllEnabled() as $f) {

            $between_from = date('Y-m-d', strtotime($f['last_cron_time']) - 24*3600);
            $between_to = date('Y-m-d 23:59:59', time() - $f['delay'] - 10*3600);
            $orders = $om->where('paid_date >= ? AND paid_date < ?', $between_from, $between_to)->fetchAll('id');
            if ($orders) {

                // Params for all orders with one query
                $params = $opm->get(array_keys($orders));

                // Customer data for all orders with one query
                $cids = array();
                foreach($orders as $o) {
                    $cids[] = $o['contact_id'];
                }
                $customers = $cm->getById($cids);

                $sent_count = 0;                // emails sent counter
                $sent_count_sms = 0;            // smses sent counter
                foreach($orders as $o) {

                    try {
                        // Is there a recipient in the first place?
                        if (empty($o['contact_id'])) {
                            if (waSystemConfig::isDebug()) {
                                waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}: no contact_id");
                            }
                            continue;
                        }

                        // Check that this is the first order of this customer
                        if ($f['first_order_only']) {
                            $first_order_id = $om->select('MIN(id)')->where('contact_id=? AND paid_date IS NOT NULL', $o['contact_id'])->fetchField();
                            if ($first_order_id != $o['id']) {
                                if (waSystemConfig::isDebug()) {
                                    waLog::log("Skipping follow-up #{$f['id']} for order #{$o['id']}: not the first order of a customer.");
                                }
                                continue;
                            }
                        }

                        $o['params'] = ifset($params[$o['id']], array());

                        $source = 'backend';
                        if (!empty($o['params']['storefront'])) {
                            $source = rtrim($o['params']['storefront'], '/').'/*';
                        }

                        if ($f['source'] && $f['source'] != $source) {
                            continue;
                        }

                        // Make sure we have not send follow-up for this order yet
                        if (isset($o['params']['followup_'.$f['id']])) {
                            if (waSystemConfig::isDebug()) {
                                waLog::log("Skipping follow-up #{$f['id']} for order #{$o['id']}: already sent before.");
                            }
                            continue;
                        }

                        $ords = array($o['id'] => $o);
                        shopHelper::workupOrders($ords, false);
                        $o = $ords[$o['id']];
                        unset($ords);

                        // Recipient info
                        //$customer = ifset($customers[$o['contact_id']], $empty_customer);
                        $contact = new shopCustomer($o['contact_id']);

                        if (self::sendFollowup($f, $o, $contact)) {
                            if ($f['transport'] === 'email') {
                                $sent_count += 1;
                            }
                            if ($f['transport'] === 'sms') {
                                $sent_count += 1;
                            }
                        }

                    } catch (Exception $e) {
                        waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}:\n".$e);
                    }
                }

                /**
                 * Notify plugins about sending followup
                 * @event followup_send
                 * @param array[string]int $params['sent_count'] number of emails successfully sent
                 * @param array[string]int $params['sent_count_sms'] number of SMSes successfully sent
                 * @param array[string]int $params['id'] followup_id
                 * @return void
                 */
                $event_params = $f;
                $event_params['sent_count'] = $sent_count;
                $event_params['send_count_sms'] = $sent_count_sms;
                wa()->event('followup_send', $event_params);
            }
            $fm->updateById($f['id'], array(
                'last_cron_time' => $between_to,
            ));
        }
    }

    public static function sendFollowup($f, $o, $contact, $to = null)
    {
        if ($f['transport'] === 'sms') {
            return self::sendSms($f, $o, $contact, $to);
        } else {
            return self::sendEmail($f, $o, $contact, $to);
        }
    }

    public static function sendSms($f, $o, $contact, $to = null)
    {
        $olm = new shopOrderLogModel();
        $opm = new shopOrderParamsModel();

        if ($to === null) {
            $phone = $contact->get('phone', 'default'); // this with throw exception if contact does not exist; that's ok
            if (!$phone) {
                if (waSystemConfig::isDebug()) {
                    waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}: contact has no phone");
                }
                return false;
            }
        } else {
            $phone = $to;
        }

        if (!self::sendOneSms($f, $o, $contact, $phone)) {
            waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}: SMS adapter returned FALSE. Check sms.log for details.");
            return false;
        }

        // Write to order log
        $olm->add(array(
            'order_id' => $o['id'],
            'contact_id' => null,
            'action_id' => '',
            'text' => sprintf_wp("Follow-up <strong>%s</strong> sent to customer.", htmlspecialchars($f['name'])),
            'before_state_id' => $o['state_id'],
            'after_state_id' => $o['state_id'],
        ));
        // Write to order params
        $opm->insert(array(
            'order_id' => $o['id'],
            'name' => 'followup_'.$f['id'],
            'value' => date('Y-m-d H:i:s'),
        ), 1);
        return true;
    }

    public static function sendEmail($f, $o, $contact, $to = null)
    {
        $olm = new shopOrderLogModel();
        $opm = new shopOrderParamsModel();

        if ($to === null) {
            $email = $contact->get('email', 'default'); // this with throw exception if contact does not exist; that's ok
            if (!$email) {
                if (waSystemConfig::isDebug()) {
                    waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}: contact has no email");
                }
                return false;
            }
            $to = array($email => $contact->getName());
        }

        if (!self::sendOneEmail($f, $o, null, $contact, $to)) {
            waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}: waMessage->send() returned FALSE.");
            return false;
        }

        // Write to order log
        $olm->add(array(
            'order_id' => $o['id'],
            'contact_id' => null,
            'action_id' => '',
            'text' => sprintf_wp("Follow-up <strong>%s</strong> sent to customer.", htmlspecialchars($f['name'])),
            'before_state_id' => $o['state_id'],
            'after_state_id' => $o['state_id'],
        ));
        // Write to order params
        $opm->insert(array(
            'order_id' => $o['id'],
            'name' => 'followup_'.$f['id'],
            'value' => date('Y-m-d H:i:s'),
        ), 1);

        return true;

    }

    public static function fetchBodyAndSubject($f, $o, $contact)
    {
        $view = wa()->getView();
        $workflow = new shopWorkflow();

        $items_model = new shopOrderItemsModel();
        $o['items'] = $items_model->getItems($o['id']);
        foreach ($o['items'] as &$i) {
            if (!empty($i['file_name'])) {
                $i['download_link'] = wa()->getRouteUrl('/frontend/myOrderDownload',
                    array('id' => $o['id'], 'code' => $o['params']['auth_code'], 'item' => $i['id']), true
                );
            }
        }
        unset($i);

        // Assign template vars
        $view->clearAllAssign();
        $view->assign('followup', $f); // row from shop_followup
        $view->assign('order', $o); // row from shop_order, + 'params' key
        $view->assign('customer', $contact); // shopCustomer
        $view->assign('order_url', wa()->getRouteUrl('/frontend/myOrderByCode', array('id' => $o['id'], 'code' => $o['params']['auth_code']), true));
        $view->assign('status', $workflow->getStateById($o['state_id'])->getName());

        // $shipping_address, $billing_address
        foreach (array('shipping', 'billing') as $k) {
            $address = shopHelper::getOrderAddress($o['params'], $k);
            $formatter = new waContactAddressOneLineFormatter(array('image' => false));
            $address = $formatter->format(array('data' => $address));
            $view->assign($k.'_address', $address['value']);
        }

        return array(
            'subject' => $view->fetch('string:'.$f['subject']),
            'body' => $view->fetch('string:'.$f['body'])
        );
    }

    /**
     * Helper to send one message: used during real sending, as well as for test emails from follow-ups settings page.
     */
    public static function sendOneEmail($f, $o, $customer, $contact, $to)
    {
        $fetched = self::fetchBodyAndSubject($f, $o, $contact);
        $subject = $fetched['subject'];
        $body = $fetched['body'];
        $general = wa('shop')->getConfig()->getGeneralSettings();
        $from = $general['email'];
        if ($f['from']) {
            $from = $f['from'];
        }

        // Send the message
        $message = new waMailMessage($subject, $body);
        $message->setTo($to);
        $message->setFrom($from, $general['name']);

        return $message->send();
    }

    public static function sendOneSms($f, $o, $contact, $to)
    {
        $fetched = self::fetchBodyAndSubject($f, $o, $contact);
        $body = $fetched['body'];
        $sms = new waSMS();
        return $sms->send($to, $body, isset($f['from']) ? $f['from'] : null);
    }

}

