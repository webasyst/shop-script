<?php

/**
 * Controller to send tests for follow-ups settings page.
 */
class shopMarketingFollowupsTestController extends shopMarketingSettingsJsonController
{
    public function execute()
    {
        $order_id = waRequest::request('order_id', 0, 'int');
        $followup_id = waRequest::request('followup_id', 0, 'int');
        $to = waRequest::request('to');

        $fm = new shopFollowupModel();
        $f = $fm->getById($followup_id);
        if (!$f) {
            $this->errors = sprintf_wp('%s entry not found', _w('Follow-up'));
            return;
        }

        $om = new shopOrderModel();
        $o = $om->getById($order_id);
        if (!$o) {
            $this->errors = _w('Order not found.');
            return;
        }
        $orders = array(
            $o['id'] => $o
        );
        shopHelper::workupOrders($orders, false);
        $o = $orders[$o['id']];
        unset($orders);

        $opm = new shopOrderParamsModel();
        $o['params'] = $opm->get($order_id);

        try {
            $contact = new shopCustomer($o['contact_id']);
            $contact->getName();
        } catch (Exception $e) {
            // Contact not found
        }
        if (empty($contact)) {
            $contact = new shopCustomer(wa()->getUser()->getId());
        }

        $contact_data = $contact->getCustomerData();
        foreach (ifempty($contact_data, array()) as $field_id => $value) {
            if ($field_id !== 'contact_id') {
                $contact[$field_id] = $value;
            }
        }

        if ($f['transport'] === 'email') {
            $to = array($to => $contact->getName());
        }

        if (!shopFollowupCli::sendFollowup($f, $o, $contact, $to)) {
            $this->errors = "Unable to send follow-up #{$f['id']} for order #{$o['id']}";
            return;
        }

        $this->response = 'ok';
    }
}
