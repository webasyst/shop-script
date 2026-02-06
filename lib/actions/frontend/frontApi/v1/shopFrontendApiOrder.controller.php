<?php

class shopFrontendApiOrderController extends shopFrontApiJsonController
{
    public function get()
    {
        $order_id = waRequest::param('order_id', null, waRequest::TYPE_INT);
        $order_payment_code = waRequest::request('code', null, waRequest::TYPE_STRING);
        if (!$order_id) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: %s.', 'order_id'), 400);
        }

        try {
            $order = new shopOrder($order_id);
            $proper_code = $order->getPaymentLinkHash();
        } catch (Exception $e) {
            throw new waAPIException('not_found', _w('Order not found.'), 404);
        }

        if ($proper_code !== $order_payment_code) {
            throw new waAPIException('incorrect_code', _w('The order payment code is incorrect.'), 403);
        }

        if (empty($order['paid_date'])) {
            try {
                $plugin = null;
                if (!$order['payment_plugin']) {
                    $methods = shopPayment::getMethodsByOrder($order);
                    if ( ( $payment_ids = waRequest::param('payment_id'))) {
                        $methods = array_intersect_key($methods, array_fill_keys((array)$payment_ids, true));
                    }
                    foreach($methods as $m) {
                        $plugin = $m['instance'] ?? shopPayment::getPlugin($m['plugin'], $m['id']);
                        if (shopPayment::pluginSupportsQRCode($plugin)) {
                            break;
                        }
                    }
                }
                shopPayment::statePolling($order, $plugin);

                // Reload order data in case state changed
                $order = new shopOrder($order['id']);
            } catch (Throwable $e) {
            }
        }

        $order['items'];
        $this->response = (new shopFrontApiOrderFormatter())->format($order->getData());
    }
}
