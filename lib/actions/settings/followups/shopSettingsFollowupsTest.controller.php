<?php

/**
 * Controller to send tests for follow-ups settings page.
 */
class shopSettingsFollowupsTestController extends waJsonController
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
            $this->errors = _w('Order not found');
            return;
        }
        $ords = array(
            $o['id'] => $o
        );
        shopHelper::workupOrders($ords, false);
        $o = $ords[$o['id']];
        unset($ords);

        $opm = new shopOrderParamsModel();
        $o['params'] = $opm->get($order_id);

        try {
            $contact = $o['contact_id'] ? new shopCustomer($o['contact_id']) : wa()->getUser();
            $contact->getName();
        } catch (Exception $e) {
            $contact = new shopCustomer(wa()->getUser()->getId());
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
