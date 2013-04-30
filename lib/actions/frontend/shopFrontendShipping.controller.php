<?php

class shopFrontendShippingController extends waJsonController
{
    public function execute()
    {
        $shipping_id = waRequest::post('shipping_id');
        $customer = waRequest::post('customer_'.$shipping_id);
        if (isset($customer['address.shipping'])) {
            $address = $customer['address.shipping'];
        } else {
            $address = array();
        }

        $plugin = shopShipping::getPlugin(null, $shipping_id);

        $cart = new shopCart();
        $shipping = new shopCheckoutShipping();
        $rates = $plugin->getRates($shipping->getItems(), $address, array('total_price' => $cart->total()));
        if (is_array($rates)) {
            foreach ($rates as $r_id => &$r) {
                $r['id'] = $r_id;
                $r['rate'] = shop_currency($r['rate'], $r['currency']);
            }
            unset($r);
            $this->response = array_values($rates);
        } else {
            $this->response = $rates;
        }
    }
}