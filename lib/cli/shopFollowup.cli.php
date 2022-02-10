<?php

/**
 * Cron job to send delayed follow-up emails to customers after change orders' state.
 * Expected to run at least once an hour.
 */
class shopFollowupCli extends waCliController
{
    public function execute()
    {
        $fm = new shopFollowupModel();
        $opm = new shopOrderParamsModel();
        $asm = new waAppSettingsModel();
        $om = new shopOrderModel();
        $olm = new shopOrderLogModel();

        $asm->set('shop', 'last_followup_cli', time());

        $where = array(
            '(datetime >= s:from)',
            '(datetime < s:to)',
            '(after_state_id = s:state_id)',
            '(after_state_id != before_state_id)',
        );
        $where = implode(' AND ', $where);
        $time = array(
            'now'    => time(),
        );
        $time['datetime'] = date('Y-m-d H:i:s', $time['now']);

        $enabled_followups = $fm->getAllEnabled();
        if ($enabled_followups) {
            /**
             * @event start_followup_cli
             * @param array [string]array $params['followups']
             * @return void
             */
            wa('shop')->event('start_followup_cli', ref(array(
                'followups' => $enabled_followups,
            )));
        }

        foreach ($enabled_followups as $f) {
            if (empty($f['last_cron_time'])) {
                $f['last_cron_time'] = $time['datetime'];
            }
            $f['last_cron_timestamp'] = strtotime($f['last_cron_time']);
            $search_params = array(
                'from'     => date('Y-m-d H:i:s', $f['last_cron_timestamp'] - $f['delay']),
                'to'       => date('Y-m-d H:i:s', $time['now'] - $f['delay']),
                'state_id' => $f['state_id'],
            );

            $order_ids = $olm
                ->select('DISTINCT order_id')
                ->where($where, $search_params)
                ->fetchAll('order_id');
            if ($order_ids) {
                $order_ids = array_keys($order_ids);
            }

            if ($order_ids) {
                $orders = $om->getById($order_ids);

                // Params for all orders with one query
                $params = $opm->get($order_ids);

                $sent_count = 0;                // email sent counter
                $sent_count_sms = 0;            // sms sent counter
                foreach ($orders as $o) {

                    try {
                        // Is there a recipient in the first place?
                        if (empty($o['contact_id'])) {
                            if (waSystemConfig::isDebug()) {
                                waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}: no contact_id", 'shop/followups.log');
                            }
                            continue;
                        }

                        if (!empty($f['same_state_id']) && ($o['state_id'] != $f['state_id'])) {
                            if (waSystemConfig::isDebug()) {
                                waLog::log("Skipping follow-up #{$f['id']} for order #{$o['id']}: not the same state_id.", 'shop/followups.log');
                            }
                            continue;
                        }

                        // Check that this is the first paid order of this customer
                        if ($f['first_order_only']) {
                            $first_paid_date = $om->select('MIN(paid_date)')->where('contact_id=? AND paid_date IS NOT NULL', $o['contact_id'])->fetchField();
                            $first_order_id = $om->select('MIN(id)')->where('contact_id=? AND paid_date=?', $o['contact_id'], $first_paid_date)->fetchField();
                            if ($first_order_id != $o['id']) {
                                if (waSystemConfig::isDebug()) {
                                    waLog::log("Skipping follow-up #{$f['id']} for order #{$o['id']}: not the first order of a customer.", 'shop/followups.log');
                                }
                                continue;
                            }
                        }

                        $o['params'] = ifset($params[$o['id']], array());

                        $source = 'backend';
                        if (!empty($o['params']['storefront'])) {
                            $source = rtrim($o['params']['storefront'], '/').'/*';
                        }

                        if (isset($f['sources'])
                            && in_array('all_sources', $f['sources']) === false
                            && in_array($source, $f['sources']) === false
                        ) {
                            if (waSystemConfig::isDebug()) {
                                waLog::log("Skipping follow-up #{$f['id']} for order #{$o['id']}: mismatch order source.", 'shop/followups.log');
                            }
                            continue;
                        }

                        // Make sure we have not send follow-up for this order yet
                        if (isset($o['params']['followup_'.$f['id']])) {
                            if (waSystemConfig::isDebug()) {
                                waLog::log("Skipping follow-up #{$f['id']} for order #{$o['id']}: already sent before.", 'shop/followups.log');
                            }
                            continue;
                        }

                        $_orders = array($o['id'] => $o);
                        shopHelper::workupOrders($_orders, false);
                        $o = $_orders[$o['id']];
                        unset($_orders);

                        // Recipient info
                        $contact = new shopCustomer($o['contact_id']);

                        $contact_data = $contact->getCustomerData();
                        foreach (ifempty($contact_data, array()) as $field_id => $value) {
                            if ($field_id !== 'contact_id') {
                                $contact[$field_id] = $value;
                            }
                        }

                        if (self::sendFollowup($f, $o, $contact)) {
                            if ($f['transport'] === 'email') {
                                ++$sent_count;
                            }
                            if ($f['transport'] === 'sms') {
                                ++$sent_count_sms;
                            }
                        }

                    } catch (Exception $e) {
                        waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}:\n".$e, 'shop/followups.log');
                    }
                }

                /**
                 * Notify plugins about sending followup
                 * @event followup_send
                 * @param array [string]int $params['sent_count'] number of emails successfully sent
                 * @param array [string]int $params['sent_count_sms'] number of SMSes successfully sent
                 * @param array [string]int $params['id'] followup_id
                 * @return void
                 */
                $event_params = $f;
                $event_params['sent_count'] = $sent_count;
                $event_params['send_count_sms'] = $sent_count_sms;
                wa('shop')->event('followup_send', $event_params);
            }

            $fm->updateById($f['id'], array(
                'last_cron_time' => $time['datetime'],
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

    /**
     * @param array $f followup
     * @param array $o order
     * @param waContact $contact
     * @param string $to phone
     * @return bool
     */
    public static function sendSms($f, $o, $contact, $to = null)
    {
        if ($to === null) {
            $phone = $contact->get('phone', 'default'); // this with throw exception if contact does not exist; that's ok
            if (!$phone) {
                if (waSystemConfig::isDebug()) {
                    waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}: contact has no phone", 'shop/followups.log');
                }
                return false;
            }
        } else {
            $phone = $to;
        }

        if (!self::sendOneSms($f, $o, $contact, $phone)) {
            waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}: SMS adapter returned FALSE. Check sms.log for details.", 'shop/followups.log');
            return false;
        }

        self::writeToOrderLog($o, $f);

        self::writeToOrderParams($o, $f);
        return true;
    }

    /**
     * @param $f
     * @param $o
     * @param waContact $contact
     * @param mixed $to email
     * @return bool
     */
    public static function sendEmail($f, $o, $contact, $to = null)
    {
        if ($to === null) {
            $email = $contact->get('email', 'default'); // this with throw exception if contact does not exist; that's ok
            if (!$email) {
                if (waSystemConfig::isDebug()) {
                    waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}: contact has no email", 'shop/followups.log');
                }
                return false;
            }
            $to = array($email => $contact->getName());
        }

        if (!self::sendOneEmail($f, $o, null, $contact, $to)) {
            waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}: waMessage->send() returned FALSE.", 'shop/followups.log');
            return false;
        }

        self::writeToOrderLog($o, $f);

        self::writeToOrderParams($o, $f);

        return true;
    }

    private static function writeToOrderParams($order, $followup)
    {
        static $opm;
        if (empty($opm)) {
            $opm = new shopOrderParamsModel();
        }
        // Write to order params
        $opm->insert(array(
            'order_id' => $order['id'],
            'name'     => 'followup_'.$followup['id'],
            'value'    => date('Y-m-d H:i:s'),
        ), 1);
    }

    private static function writeToOrderLog($order, $followup)
    {
        static $olm;
        if (empty($olm)) {
            $olm = new shopOrderLogModel();
        }
        $name = htmlspecialchars($followup['name'], ENT_QUOTES, 'utf-8');
        $olm->add(array(
            'order_id'        => $order['id'],
            'contact_id'      => null,
            'action_id'       => '',
            'text'            => sprintf_wp("Follow-up <strong>%s</strong> sent to customer.", $name),
            'before_state_id' => $order['state_id'],
            'after_state_id'  => $order['state_id'],
        ));
    }

    private static function getOrderUrl($o)
    {
        $storefront = ifset($o['params']['storefront'], '');
        if (!$storefront) {
            // not storefront - get first storefront
            $storefronts = shopHelper::getStorefronts();
            $storefront = (string)reset($storefronts);
        }

        $order_domain = shopHelper::getDomainByStorefront($storefront);
        $order_url = wa()->getRouteUrl('/frontend/myOrderByCode', array('id' => $o['id'], 'code' => $o['params']['auth_code']), true, $order_domain);
        return $order_url;
    }

    public static function fetchBodyAndSubject($f, $o, $contact)
    {
        $view = wa()->getView();
        $workflow = new shopWorkflow();

        $items_model = new shopOrderItemsModel();
        $o['items'] = $items_model->getItems($o['id']);
        foreach ($o['items'] as &$i) {
            if (!empty($i['file_name'])) {
                $i['download_link'] = wa()->getRouteUrl(
                    '/frontend/myOrderDownload',
                    array(
                        'id'   => $o['id'],
                        'code' => $o['params']['auth_code'],
                        'item' => $i['id'],
                    ),
                    true
                );
            }
        }
        unset($i);

        $order_url = self::getOrderUrl($o);
        $signup_url = ifset($o['params']['signup_url'], '');

        // Assign template vars
        $view->clearAllAssign();
        $view->assign('followup', $f); // row from shop_followup
        $view->assign('order', $o); // row from shop_order, + 'params' key
        $view->assign('customer', $contact); // shopCustomer
        $view->assign('order_url', $order_url);
        $view->assign('signup_url', $signup_url);
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
            'body'    => $view->fetch('string:'.$f['body'])
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
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $general = $config->getGeneralSettings();
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
