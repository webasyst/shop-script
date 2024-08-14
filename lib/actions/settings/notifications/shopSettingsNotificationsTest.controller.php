<?php

/**
 * Controller to send tests for follow-ups settings page.
 */
class shopSettingsNotificationsTestController extends waJsonController
{
    public function execute()
    {
        $order_id = waRequest::request('order_id', 0, 'int');
        $id = waRequest::request('id', 0, 'int');
        $to = waRequest::request('to');

        $nm = new shopNotificationModel();
        $n = $nm->getById($id);
        if (!$n) {
            $this->errors = sprintf_wp('%s entry not found', _w('Notification'));
            return;
        }

        $om = new shopOrderModel();
        $o = $om->getById($order_id);
        if (!$o) {
            $this->errors = _w('Order not found.');
            return;
        }

        $opm = new shopOrderParamsModel();
        $o['params'] = $opm->get($order_id, true);

        try {
            $contact = $o['contact_id'] ? new shopCustomer($o['contact_id']) : wa()->getUser();
            $contact->getName();
        } catch (Exception $e) {
            $contact = new shopCustomer(wa()->getUser()->getId());
        }

        $workflow = new shopWorkflow();

        // send notifications
        try {
            shopNotifications::sendOne($id, array(
                'order'    => $o,
                'customer' => $contact,
                'status'   => $workflow->getStateById($o['state_id'])->getName(),
            ), $to);
        } catch (Exception $ex) {
            $this->errors = $ex->getMessage();
        }
    }
}
