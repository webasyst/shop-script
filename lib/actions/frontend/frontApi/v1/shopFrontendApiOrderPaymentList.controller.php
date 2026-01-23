<?php

class shopFrontendApiOrderPaymentListController extends shopFrontApiJsonController
{
    public function post()
    {
        return $this->get();
    }

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

        $methods = shopPayment::getMethodsByOrder($order);
        if (!empty($order['params']['payment_id'])) {
            // In case order already has a payment plugin selected, only allow options from that plugin
            $methods = array_intersect_key($methods, [$order['params']['payment_id'] => 1]);
        } else if ( ( $payment_ids = waRequest::param('payment_id'))) {
            $methods = array_intersect_key($methods, array_fill_keys((array)$payment_ids, true));
        }

        $host_url = wa()->getConfig()->getHostUrl();

        // Options to pay via link
        $payment_options = array_map(function($o) use ($host_url) {
            return [
                'id' => $o['index'] === null ? "{$o['id']}.link" : "{$o['id']}.{$o['index']}.link",
                'type' => 'link',
                'plugin_id' => $o['id'],
                'logo' => $host_url.$o['logo'],
            ] + $o;
        }, shopPayment::getPaymentOptions($methods, $order));

        // Options to pay via image
        foreach($methods as $m) {
            if ($m['instance'] instanceof waIPaymentImage) {
                $payment_options[] = [
                    'id' => "{$m['id']}.image",
                    'type' => 'image',
                    'plugin_id' => $m['id'],
                    'plugin' => $m['instance']->getId(),
                    'name' => $m['name'],
                    'description' => $m['description'],
                    'logo' => $host_url.$m['logo'],
                    'index' => null,
                ];
            }
        }
        unset($m);

        $this->response = $payment_options;
    }
}
