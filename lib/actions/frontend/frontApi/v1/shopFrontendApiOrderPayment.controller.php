<?php

class shopFrontendApiOrderPaymentController extends shopFrontApiJsonController
{
    public function post()
    {
        $order_id = waRequest::param('order_id', null, waRequest::TYPE_INT);
        $order_payment_code = waRequest::request('code', null, waRequest::TYPE_STRING);
        $payment_method_id = waRequest::request('payment_id', null, waRequest::TYPE_STRING);
        if (!$order_id) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: %s.', 'order_id'), 400);
        }
        if (!$payment_method_id) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: %s.', 'payment_method_id'), 400);
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

        $payment_id_parts = explode('.', $payment_method_id);
        $payment_id = $payment_id_parts[0];
        $payment_type = end($payment_id_parts);
        $methods = array_intersect_key($methods, [$payment_id => 1]);
        if (empty($methods) || !in_array($payment_type, ['link', 'image'])) {
            throw new waAPIException('bad_payment_id', _w('Payment method not found.'), 400);
        }

        $order_set_params = waRequest::request('params', [], waRequest::TYPE_ARRAY);
        if ($order_set_params) {
            $order_set_params = array_diff_key($order_set_params, ['payment_id' => 1, 'payment_plugin' => 1]);
            if ($order_set_params) {
                $order['params'] = array_merge($order['params'], $order_set_params);
                (new shopOrderParamsModel())->set($order['id'], $order_set_params, false);
            }
        }

        if ($payment_type === 'image') {
            $this->response = $this->getResponseImage(reset($methods), $order);
        } else { // $payment_type === 'link'
            $this->response = $this->getResponseLink($methods, $order, $payment_method_id);
        }
    }

    protected function getResponseLink(array $methods, shopOrder $order, string $payment_method_id): array
    {
        $payment_options = array_map(function($o) use ($payment_method_id) {
            $id = $o['index'] === null ? "{$o['id']}.link" : "{$o['id']}.{$o['index']}.link";
            if ($payment_method_id === $id) {
                return $o;
            }
            return null;
        }, shopPayment::getPaymentOptions($methods, $order));
        $payment_options = array_filter($payment_options);
        if (!$payment_options) {
            throw new waAPIException('bad_payment_id', _w('Payment method not found.'), 400);
        }
        $payment_option = reset($payment_options);
        $response = [
            'payment_url' => wa()->getRouting()->getUrl('shop/frontend/paymentLink', [
                'hash' => $order->getPaymentLinkHash(),
            ], true).'?payment_id='.explode('.', $payment_method_id, 2)[0],
        ];
        if ($payment_option['index'] !== null) {
            $response['payment_url'] .= '&index='.$payment_option['index'];
        }
        return $response;
    }

    protected function getResponseImage(array $m, shopOrder $order): array
    {
        $plugin = $m['instance'] ?? shopPayment::getPlugin($m['plugin'], $m['id']);
        if (!$plugin instanceof waIPaymentImage) {
            throw new waAPIException('bad_payment_id', _w('The payment method does not support payment by image.'), 400);
        }

        try {
            $m['order_data'] = $m['order_data'] ?? shopPayment::getOrderData($order['id'], $plugin);
            $payment_image = $plugin->image($m['order_data']);
            if ($payment_image) {
                $response = [
                    'payment_image_url' => ifempty($payment_image, 'image_data_url', ifset($payment_image, 'image_url', '')),
                ];
                if ($response['payment_image_url']) {
                    if (!empty($payment_image['qr_payload'])) {
                        $response['qr_payload'] = $payment_image['qr_payload'];
                    }
                    return $response;
                }
            }
        } catch (Throwable $e) {
        }
        throw new waAPIException('payment_plugin_error', _w('Failed to generate a payment image.'), 400);
    }
}
