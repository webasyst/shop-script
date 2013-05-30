<?php

class shopFrontendShippingController extends waJsonController
{
    public function execute()
    {

        $cart = new shopCart();
        $total = $cart->total();

        $shipping = new shopCheckoutShipping();
        $items = $shipping->getItems();

        if (waRequest::method() == 'post') {
            $shipping_id = waRequest::post('shipping_id');
            $customer = waRequest::post('customer_'.$shipping_id);
            if (isset($customer['address.shipping'])) {
                $address = $customer['address.shipping'];
            } else {
                $address = array();
            }

            if ($shipping_id) {
                $this->response = $this->getRates($shipping_id, $items, $address, $total);
            } else {
                $this->errors = _w('Shipping is required');
            }
        } elseif ($shipping_ids = waRequest::get('shipping_id', array(), waRequest::TYPE_ARRAY_INT)) {
            $address = $shipping->getAddress();
            $empty = true;
            foreach ($address as $v) {
                if ($v) {
                    $empty = false;
                    break;
                }
            }
            if ($empty) {
                $address = array();
            }
            if (!$address) {
                $settings = wa('shop')->getConfig()->getCheckoutSettings();
                if ($settings['contactinfo']['fields']['address']) {
                    foreach ($settings['contactinfo']['fields']['address']['fields'] as $k => $f) {
                        if (!empty($f['value'])) {
                            $address[$k] = $f['value'];
                        }
                    }
                }
            }
            foreach ($shipping_ids as $shipping_id) {
                $this->response[$shipping_id] = $this->getRates($shipping_id, $items, $address, $total);
            }
        }
    }

    protected function getRates($shipping_id, $items, $address, $total)
    {
        $plugin = shopShipping::getPlugin(null, $shipping_id);
        $weight_unit = $plugin->allowedWeightUnit();
        $dimension = shopDimension::getInstance()->getDimension('weight');
        if ($weight_unit != $dimension['base_unit']) {
            foreach ($items as $item_id => $item) {
                if ($item['weight']) {
                    $items[$item_id]['weight'] = $item['weight'] * $dimension['units'][$weight_unit]['multiplier'];
                }
            }
        }
        $currency = $plugin->allowedCurrency();
        $currrent_currency = wa()->getConfig()->getCurrency(false);
        if ($currency != $currrent_currency) {
            $total = shop_currency($total, $currrent_currency, $currency, false);
        }
        $rates = $plugin->getRates($items, $address, array('total_price' => $total));
        if (is_array($rates)) {
            foreach ($rates as $r_id => &$r) {
                $r['id'] = $r_id;
                $r['rate'] = shop_currency($r['rate'], $r['currency']);
            }
            unset($r);
            return array_values($rates);
        }
        return $rates;
    }
}