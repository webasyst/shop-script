<?php

/**
 * Single order page in mobile backend.
 */
class shopOrdersInfoMobileAction extends shopMobileViewAction
{
    public function execute()
    {
        $id = waRequest::request('id', 0, 'int');
        if (!$id) {
            $this->redirect(wa()->getAppUrl());
        }

        // Order
        $om = new shopOrderModel();
        $order = $om->getOrder($id);
        shopHelper::workupOrders($order, true);
        $order['tax'] = (float) $order['tax'];
        $order['discount'] = (float) $order['discount'];

        // Order params
        $opm = new shopOrderParamsModel();
        $order['params'] = $opm->get($order['id']);

        // Order subtotal
        $order_subtotal = 0;
        foreach($order['items'] as $i) {
            $order_subtotal += $i['price']*$i['quantity'];
        }

        // Format addresses
        $settings = wa('shop')->getConfig()->getCheckoutSettings();
        $form_fields = ifset($settings['contactinfo']['fields'], array());
        $formatter = new waContactAddressOneLineFormatter();
        if (isset($form_fields['address.shipping'])) {
            $shipping_address = shopHelper::getOrderAddress($order['params'], 'shipping');
            $shipping_address = $formatter->format(array('data' => $shipping_address));
            $shipping_address = $shipping_address['value'];
        } else {
            $shipping_address = null;
        }
        if (isset($form_fields['address.billing'])) {
            $billing_address = shopHelper::getOrderAddress($order['params'], 'billing');
            $billing_address = $formatter->format(array('data' => $billing_address));
            $billing_address = $billing_address['value'];
        } else {
            $billing_address = null;
        }

        // Order history
        $log_model = new shopOrderLogModel();
        $log = $log_model->getLog($order['id']);
        $order_comment = '';
        foreach($log as $l) {
            if ($l['action_id'] == 'create') {
                $order_comment = $l['text'];
                break;
            }
        }

        // Customer
        $contact = $customer = self::getCustomer($order);
        $top = array();
        foreach (array('email', 'phone') as $f) {
            if ( ( $v = $contact->get($f, 'top,html'))) {
                $top[] = array(
                    'id' => $f,
                    'name' => waContactFields::get($f)->getName(),
                    'value' => is_array($v) ? implode(', ', $v) : $v,
                );
            }
        }

        // Workflow stuff: actions and state
        $workflow = new shopWorkflow();
        $workflow_state = $workflow->getStateById($order['state_id']);
        $workflow_buttons = array();
        foreach ($workflow_state->getActions() as $a_id => $action) {
            if ($a_id === 'edit' || $a_id === 'delete') {
                continue;
            }
            $workflow_buttons[] = $action->getButton();
        }

        $this->view->assign('top', $top);
        $this->view->assign('log', $log);
        $this->view->assign('order', $order);
        $this->view->assign('uniqid', uniqid('f'));
        $this->view->assign('customer', $customer);
        $this->view->assign('order_comment', $order_comment);
        $this->view->assign('workflow_state', $workflow_state);
        $this->view->assign('workflow_buttons', $workflow_buttons);
        $this->view->assign('shipping_address', $shipping_address);
        $this->view->assign('billing_address', $billing_address);
        $this->view->assign('order_subtotal', $order_subtotal);
        $this->view->assign('currency', ifempty($order['currency'], wa()->getConfig()->getCurrency()));

        wa()->getResponse()->setTitle(_w('Order').' '.$order['id_str']);
        parent::execute();
    }

    protected static function getCustomer($order)
    {
        $customer = null;
        if ($order['contact_id']) {
            try {
                $customer = new shopCustomer($order['contact_id']);
                $customer->getName();
            } catch (Exception $e) {
                $customer = null;
            }
        }
        if (!$customer) {
            $customer = new shopCustomer();
            try {
                $customer['name'] = ifset($order['params']['contact_name'], '');
                $customer['email'] = ifset($order['params']['contact_email'], '');
                $customer['phone'] = ifset($order['params']['contact_phone'], '');
            } catch (Exception $e) {}
        }
        return $customer;
    }
}

