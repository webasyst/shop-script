<?php

class shopWorkflowInitpayAction extends shopWorkflowAction
{
    public function isAvailable($order)
    {
        if (empty($order['id'])) {
            return true;
        }
        if (!($order instanceof shopOrder)) {
            $order = new shopOrder($order['id']);
        }
        if (!$order['payment_plugin']) {
            return false;
        }
        $payment_plugin_info = waPayment::info($order['payment_plugin']->getId());
        return !empty($payment_plugin_info['pos_initiates_payment']);
    }

    public function execute($params = null)
    {
        if (is_array($params)) {
            $order_id = $params['order_id'];
        } else {
            $order_id = $params;
        }

        $order = new shopOrder($order_id);
        if (!$order['payment_plugin']) {
            throw new waException('Order has no payment selected');
        }
        $payment_plugin_info = waPayment::info($order['payment_plugin']->getId());
        if (empty($payment_plugin_info['pos_initiates_payment'])) {
            throw new waException('Selected payment type for order does not support initpay');
        }

        $error_msg = _w('Selected plugin is unable to initialize payment.');
        try {
            $init_error = $order['payment_plugin']->payment([], shopPayment::getOrderData($order['id'], $order['payment_plugin']));
        } catch (Exception $e) {
            $init_error = $e->getMessage();
        }
        if ($init_error) {
            if (is_string($init_error)) {
                $error_msg .= ' '.$init_error;
            }
            throw new waException($error_msg);
        }
    }
}
