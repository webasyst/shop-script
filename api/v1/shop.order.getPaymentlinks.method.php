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
            throw new waAPIException('not_fount', 'Order not found', 404);
        }

        if (!$order['state']->paymentAllowed()) {
            throw new waAPIException('order_cant_be_paid', 'Payment is not allowed for this order', 400);
        }

        $this->response = [];

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
            ];
        }
    }
}
