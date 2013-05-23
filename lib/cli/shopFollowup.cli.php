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
        $olm = new shopOrderLogModel();
        $cm = new shopCustomerModel();
        $om = new shopOrderModel();

        $asm->set('shop', 'last_followup_cli', time());

        $view = wa()->getView();
        $empty_customer = $cm->getEmptyRow();
        $general = wa('shop')->getConfig()->getGeneralSettings();

        foreach($fm->getAll() as $f) {
            $between_from = date('Y-m-d', strtotime($f['last_cron_time']) - 24*3600);
            $between_to = date('Y-m-d 23:59:59', time() - $f['delay'] - 10*3600);
            $orders = $om->where('paid_date >= ? AND paid_date < ?', $between_from, $between_to)->fetchAll('id');
            if ($orders) {

                $f_param_key = 'followup_'.$f['id'];

                // Params for all orders with one query
                $params = $opm->get(array_keys($orders));

                // Customer data for all orders with one query
                $cids = array();
                foreach($orders as $o) {
                    $cids[] = $o['contact_id'];
                }
                $customers = $cm->getById($cids);

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
                            $first_order_id = $om->select('MIN(id)')->where('contact_id=? AND paid_date IS NOT NULL', $o['contact_id']);
                            if ($first_order_id != $o['id']) {
                                if (waSystemConfig::isDebug()) {
                                    waLog::log("Skipping follow-up #{$f['id']} for order #{$o['id']}: not the first order of a customer.");
                                }
                                continue;
                            }
                        }

                        $o['params'] = ifset($params[$o['id']], array());

                        // Make sure we have not send follow-up for this order yet
                        if (isset($o['params'][$f_param_key])) {
                            if (waSystemConfig::isDebug()) {
                                waLog::log("Skipping follow-up #{$f['id']} for order #{$o['id']}: already sent before.");
                            }
                            continue;
                        }

                        shopHelper::workupOrders($o, true);

                        // Recipient info
                        $customer = ifset($customers[$o['contact_id']], $empty_customer);
                        $contact = new shopCustomer($o['contact_id']);
                        $email = $contact->get('email', 'default'); // this with throw exception if contact does not exist; that's ok
                        if (!$email) {
                            if (waSystemConfig::isDebug()) {
                                waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}: contact has no email");
                            }
                            continue;
                        }
                        $to = array($email => $contact->getName());

                        if (self::sendOne($f, $o, $customer, $contact, $to, $view, $general)) {
                            // Write to order log
                            $olm->add(array(
                                'order_id' => $o['id'],
                                'contact_id' => null,
                                'action_id' => '',
                                'text' => sprintf_wp("Follow-up <strong>%s</strong> (%s) sent to customer.", htmlspecialchars($f['name']), $f['id']),
                                'before_state_id' => $o['state_id'],
                                'after_state_id' => $o['state_id'],
                            ));
                            // Write to order params
                            $opm->insert(array(
                                'order_id' => $o['id'],
                                'name' => $f_param_key,
                                'value' => date('Y-m-d H:i:s'),
                            ));
                        } else {
                            waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}: waMessage->send() returned FALSE.");
                        }
                    } catch (Exception $e) {
                        waLog::log("Unable to send follow-up #{$f['id']} for order #{$o['id']}:\n".$e);
                    }
                }
            }
            $fm->updateById($f['id'], array(
                'last_cron_time' => $between_to,
            ));
        }
    }

    /**
     * Helper to send one message: used during real sending, as well as for test emails from follow-ups settings page.
     */
    public static function sendOne($f, $o, $customer, $contact, $to, $view=null, $general=null)
    {
        if (!$view) {
            $view = wa()->getView();
        }
        if (!$general) {
            $general = wa('shop')->getConfig()->getGeneralSettings();
        }

        $workflow = new shopWorkflow();

        $items_model = new shopOrderItemsModel();
        $o['items'] = $items_model->getItems($o['id']);
        foreach ($data['order']['items'] as &$i) {
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

        // Build email from template
        $subject = $view->fetch('string:'.$f['subject']);
        $body = $view->fetch('string:'.$f['body']);

        // Send the message
        $message = new waMailMessage($subject, $body);
        $message->setTo($to);
        $message->setFrom($general['email'], $general['name']);

        return $message->send();
    }
}

