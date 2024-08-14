<?php

class shopOrderGetPaymentlinksMethod extends shopApiMethod
{
    protected $method = 'GET';

    public function execute()
    {
        $order_id = $this->get('id', true);

        try {
            $order = new shopOrder($order_id);
            $hash = $order->getPaymentLinkHash();
        } catch (waException $e) {
            throw new waAPIException('not_fount', _w('Order not found.'), 404);
        }

        if (!$order['state']->paymentAllowed()) {
            throw new waAPIException('order_cant_be_paid', _w('Payment is not available for this order.'), 400);
        }

        $this->response = [];

        $payment_image = [];
        if ($this->get('payment_image')) {
            $payment_plugin = $order['payment_plugin'];
            if ($payment_plugin && $payment_plugin instanceof waIPaymentImage) {
                $wa_order = shopPayment::getOrderData($order['id'], $payment_plugin);
                $payment_image_data = $payment_plugin->image($wa_order);
                $payment_image = [
                    'payment_image_url' => ifset($payment_image_data, 'image_url', null),
                    'payment_image_data_url' => ifset($payment_image_data, 'image_data_url', null),
                ];
            }
        }

        $routing = wa()->getRouting();
        $storefronts = (new shopStorefrontList())->getAll(true);
        foreach($storefronts as $storefront) {
            $route_url = ifset($storefront, 'route', 'url', null);
            if (!$route_url) {
                continue;
            }
            $domain_url = $routing->getUrl('shop/frontend/paymentLink', [
                'hash' => $hash,
            ], true, $storefront['domain'], $route_url);

            $this->response[] = [
                'storefront_url' => $storefront['url'],
                'storefront_url_decoded' => $storefront['url_decoded'],
                'payment_url' => $domain_url,

                //'domain' => $storefront['domain'],
                //'route_url' => $route_url,
            ] + $payment_image;
        }
    }
}
