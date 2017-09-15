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
                //$shipping->getRate($shipping_id);
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
            //XXX use shopCheckoutShipping class
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
            $round_shipping = wa()->getSetting('round_shipping');
            $currency = $plugin->allowedCurrency();
            $config = wa('shop')->getConfig();
            /**
             * @var shopConfig $config
             */
            $current_currency = $config->getCurrency(false);
            if ($currency != $current_currency) {
                $total = shop_currency($total, $current_currency, $currency, false);
            }

            foreach ($items as &$item) {
                if (!empty($item['currency'])) {
                    if ($item['currency'] != $currency) {
                        $item['price'] = shop_currency($item['price'], $item['currency'], $currency, false);
                    }
                    unset($item['currency']);
                }
            }
            unset($item);

            $params = array(
                'total_price' => $total,
            );
            if ($shipping_params = waRequest::post('shipping_'.$shipping_id)) {
                $params['shipping_params'] = $shipping_params;
            }
            $rates = $plugin->getRates($items, $address, $params);
            if (is_array($rates)) {
                $is_html = waRequest::request('html');
                foreach ($rates as $r_id => &$r) {
                    $r['id'] = $r_id;
                    if (!isset($r['rate'])) {
                        $r['rate'] = null;
                    } elseif (is_array($r['rate'])) {
                        if ($r['rate']) {
                            $r['rate'] = max($r['rate']);
                        } else {
                            $r['rate'] = null;
                        }
                    }
                    if ($r['rate'] !== null) {
                        // Apply rounding. This converts all rates to current frontend currency.
                        if ($r['rate'] && $round_shipping) {
                            $r['rate'] = shopRounding::roundCurrency(shop_currency($r['rate'], $r['currency'], $current_currency, false), $current_currency);
                            $r['currency'] = $current_currency;
                        }

                        $r['rate_html'] = $is_html ? shop_currency_html($r['rate'], $r['currency']) : shop_currency($r['rate'], $r['currency']);
                        $r['rate'] = shop_currency($r['rate'], $r['currency']);
                    }
                }
                unset($r);
                return array_values($rates);
            } elseif (!$rates) {
                return _w('Not available');
            }
        } catch (waException $ex) {
            return $ex->getMessage();
        }
        return $rates;
    }
}
