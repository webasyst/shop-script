<?php

/**
 * @method shopConfig getConfig()
 * */
class shopFrontendShippingController extends waJsonController
{
    public function execute()
    {
        $shipping = new shopCheckoutShipping();
        $items = $shipping->getItems();

        $shipping_params = shopShipping::getItemsTotal($items);
        $cart = new shopCart();
        $shipping_params['total_price'] = $cart->total();

        if (waRequest::method() == 'post') {
            wa()->getStorage()->close();
            $shipping_id = waRequest::post('shipping_id');
            $customer = waRequest::post('customer_'.$shipping_id);

            $address = ifset($customer['address.shipping'], array());

            if ($shipping_id) {
                $rates = $this->getRates($shipping_id, $items, $address, $shipping_params);
                $this->response = $this->formatRates($rates);
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
                $settings = $this->getConfig()->getCheckoutSettings();
                if ($settings['contactinfo']['fields']['address']) {
                    foreach ($settings['contactinfo']['fields']['address']['fields'] as $k => $f) {
                        if (!empty($f['value'])) {
                            $address[$k] = $f['value'];
                        }
                    }
                }
            }

            wa()->getStorage()->close();
            waNet::multiQuery('shop.shipping');

            foreach ($shipping_ids as $shipping_id) {
                $this->response[$shipping_id] = $this->getRates($shipping_id, $items, $address, $shipping_params);
            }

            waNet::multiQuery('shop.shipping');

            foreach ($this->response as &$rates) {
                $rates = $this->formatRates($rates);
                unset($rates);
            }
        }
    }

    /**
     * @param int   $shipping_id
     * @param array $items
     * @param array $address
     * @param array $shipping_params
     * @return array|mixed|string
     * @throws waException
     */
    protected function getRates($shipping_id, $items, $address, $shipping_params)
    {
        try {
            //XXX use shopCheckoutShipping class
            $plugin_info = shopShipping::getPluginInfo($shipping_id);

            $plugin = shopShipping::getPlugin($plugin_info['plugin'], $plugin_info['id']);

            $params = shopShipping::workupShippingParams($shipping_params, $plugin, $plugin_info);
            $params['shipping_params'] = shopShipping::getParams($plugin_info['id']);

            # convert dimensions
            shopShipping::convertItemsDimensions($items, $plugin);
            $currency = $plugin->allowedCurrency();

            foreach ($items as &$item) {
                if (!empty($item['currency'])) {
                    if ($item['currency'] != $currency) {
                        $item['price'] = shop_currency($item['price'], $item['currency'], $currency, false);
                    }
                    unset($item['currency']);
                }
            }
            unset($item);

            $rates = $plugin->getRates($items, $address, $params);
        } catch (waException $ex) {
            return $ex->getMessage();
        }

        return $rates;
    }

    protected function formatRates($rates)
    {
        if ($rates instanceof waShipping) {
            $rates = $rates->getPromise();
        }
        if (is_array($rates)) {
            $is_html = waRequest::request('html', 1);
            // When free shipping coupon is used, display all rates as 0
            $checkout_data = wa('shop')->getStorage()->read('shop/checkout');
            $free_shipping = false;
            if (!empty($checkout_data['coupon_code'])) {
                empty($cm) && ($cm = new shopCouponModel());
                $coupon = $cm->getByField('code', $checkout_data['coupon_code']);
                if ($coupon && $coupon['type'] == '$FS') {
                    $free_shipping = true;
                }
            }
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
                    if ($free_shipping) {
                        $r['rate'] = 0;
                    }
                    $round_shipping = wa()->getSetting('round_shipping');
                    // Apply rounding. This converts all rates to current frontend currency.
                    if ($r['rate'] && $round_shipping) {

                        $config = wa('shop')->getConfig();
                        /** @var shopConfig $config */
                        $current_currency = $config->getCurrency(false);
                        $r['rate'] = shopRounding::roundCurrency(shop_currency($r['rate'], $r['currency'], $current_currency, false), $current_currency);
                        $r['currency'] = $current_currency;
                    }

                    $r['rate_html'] = $is_html ? shop_currency_html($r['rate'], $r['currency']) : shop_currency($r['rate'], $r['currency']);
                    $r['rate'] = shop_currency($r['rate'], $r['currency']);
                }
                $r += array(
                    'est_delivery' => '',
                    'comment'      => null,
                );
            }
            unset($r);

            return array_values($rates);
        } elseif (!$rates) {
            return _w('Not available');
        } else {
            return $rates;
        }
    }
}
