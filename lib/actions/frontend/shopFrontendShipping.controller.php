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
            wa()->getStorage()->close();
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
            wa()->getStorage()->close();
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
                $config = wa('shop')->getConfig();
                /**
                 * @var shopConfig $config
                 */
                $settings = $config->getCheckoutSettings();
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

    /**
     * @param int $shipping_id
     * @param array $items
     * @param array $address
     * @param float $total
     * @return array|mixed|string
     * @throws waException
     */
    protected function getRates($shipping_id, $items, $address, $total)
    {
        try {
            $plugin = shopShipping::getPlugin(null, $shipping_id);
            $weight_unit = $plugin->allowedWeightUnit();
            $dimension = shopDimension::getInstance()->getDimension('weight');
            if ($weight_unit != $dimension['base_unit']) {
                foreach ($items as $item_id => $item) {
                    if ($item['weight']) {
                        $items[$item_id]['weight'] = $item['weight'] / $dimension['units'][$weight_unit]['multiplier'];
                    }
                }
            }
            $currency = $plugin->allowedCurrency();
            $config = wa('shop')->getConfig();
            /**
             * @var shopConfig $config
             */
            $current_currency = $config->getCurrency(false);
            if ($currency != $current_currency) {
                $total = shop_currency($total, $current_currency, $currency, false);
            }
            $rates = $plugin->getRates($items, $address, array('total_price' => $total));
            if (is_array($rates)) {
                $is_html = waRequest::request('html');
                foreach ($rates as $r_id => &$r) {
                    $r['id'] = $r_id;
                    if ($r['rate'] !== null) {
                        $r['rate_html'] = $is_html ? shop_currency_html($r['rate'], $r['currency']) : shop_currency($r['rate'], $r['currency']);
                        $r['rate'] = shop_currency($r['rate'], $r['currency']);
                    }
                }
                unset($r);
                return array_values($rates);
            } elseif (!$rates) {
                // @todo: translate
                return _w('Not available');
            }
        } catch (waException $ex) {
            return $ex->getMessage();
        }
        return $rates;
    }
}
