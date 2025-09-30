<?php

/**
 * /shipping/costs
 */
class shopFrontendApiShippingCostsController extends shopFrontApiJsonController
{
    public function get()
    {
        return $this->post();
    }

    public function post()
    {
        $token = waRequest::request('customer_token', null, waRequest::TYPE_STRING_TRIM);
        if (empty($token)) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: %s.', 'customer_token'), 400);
        }

        $address = waRequest::request('address', null, waRequest::TYPE_ARRAY);
        $address = array_intersect_key($address, ['country' => 1, 'city' => 1, 'region' => 1, 'zip' => 1]);
        $address = array_filter($address, function($v) {
            return is_string($v);
        });
        if (empty($address['country']) || empty($address['region']) || empty($address['city'])) {
            throw new waAPIException('required_param', sprintf_wp('Missing required parameter: %s.', _w('“address” with “country”, “region” and ”city” fields’ values')), 400);
        }

        $coupon_code = waRequest::request('coupon_code', null, waRequest::TYPE_STRING_TRIM);
        $order = $this->makeShopOrder($token, $coupon_code);

        // This calculates discounts and internally modifies $order->items
        $order->discount;

        // Discount coupons (and other plugins) may force free shipping for an order
        $is_free_shipping = ifempty($data, 'order', 'coupon', 'type', '') === '$FS';
        if ($order->shipping !== null) {
            if (!$is_free_shipping && $order->shipping == 0) {
                $is_free_shipping = true;
            }
        }

        // Order items to pass to shipping plugins to calculate rates
        $items = $order->items;

        // Fetch list of shipping plugins enabled for current storefront
        // Ask them to provide shipping variants using $address given by customer.
        $config = $this->getCheckoutConfig();
        $rates = $config->getShippingRates($address, $items, shopCheckoutConfig::CUSTOMER_TYPE_PERSON);

        $services_flat = [];
        foreach ($rates as $id => $rate) {
            if (isset($rate['type'])) {
                $services_flat[$id] = $rate;
            }
        }

        $shipping_types = [
            'pickup' => [
                'id'             => 'pickup',
                'name'           => $config['shipping']['pickuppoint_name'],
            ],
            'todoor' => [
                'id'             => 'todoor',
                'name'           => $config['shipping']['courier_name'],
            ],
            'post'   => [
                'id'             => 'post',
                'name'           => $config['shipping']['post_name'],
            ],
        ];

        $services_formatted = [];
        $proper_variant_is_selected = false;
        $formatter = new shopFrontApiShippingVariantFormatter();
        $order_currency = wa('shop')->getConfig()->getCurrency(false);
        foreach ($services_flat as $s_id => $s) {
            if (!isset($s['type']) || !isset($shipping_types[$s['type']])) {
                continue;
            }

            // Apply free shipping coupon if set
            if ($is_free_shipping) {
                $s['rate'] = 0;
            } else if ($s['currency'] != $order_currency) {
                $s['rate'] = shop_currency($s['rate'], $s['currency'], $order_currency, false);
            }
            $s['currency'] = $order_currency;

            // Pickup has its own delivery date field
            if (isset($s['custom_data']['pickup']['interval'])) {
                $s['delivery_date'] = $s['custom_data']['pickup']['interval'];
            }

            $services_formatted[] = $formatter->format(['variant_id' => $s_id] + $s);
        }

        $this->response = [
            'shipping_types' => array_values($shipping_types),
            'shipping' => $services_formatted,
        ];
    }
}
