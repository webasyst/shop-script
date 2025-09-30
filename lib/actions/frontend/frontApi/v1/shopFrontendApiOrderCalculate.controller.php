<?php
/**
 * /order/calculate
 */
class shopFrontendApiOrderCalculateController extends shopFrontApiJsonController
{
    public function post()
    {
        $data = $this->processCalculate();
        $order = $data['order'];

        $formatter = new shopFrontApiItemFormatter();
        $items = array_map(function($item) use ($order, $formatter) {
            unset($item['product']);
            $item = $formatter->format($item);
            $item['value'] = $item['price']*$item['quantity'];
            return shopFrontApiFormatter::formatPriceField($item, ['price', 'value'], $order['currency']);
        }, $order['items']);

        $this->response = [
            'currency' => $order['currency'],
            'shipping' => $order->shipping ?? 0,
            'discount' => $order->discount ?? 0,
            'total' => $order->total,
            'tax' => $order->tax ?? 0,
            'items' => $items,
        ];
        $this->response = shopFrontApiFormatter::formatPriceField($this->response, ['shipping', 'total', 'discount', 'tax'], $order['currency']);
    }

    protected function processCalculate()
    {
        if (wa()->getSetting('headless_api_antispam_enabled', false, 'shop')) {
            $this->checkAntispamHash();
        }

        $customer_token = waRequest::request('customer_token', '', waRequest::TYPE_STRING_TRIM);
        $coupon_code = waRequest::request('coupon_code', null, waRequest::TYPE_STRING_TRIM);

        $config = $this->getCheckoutConfig();
        $order = $this->makeShopOrder($customer_token, $coupon_code);

        $contact = $this->getCustomerContact();
        $order['contact_id'] = null;
        $order['contact'] = $contact;

        $data = [
            'order' => $order,
            'contact' => $contact,
        ];

        if (!empty($config['shipping']['used'])) {
            $data['shipping'] = $this->getShippingData($config, $order);
            if (isset($data['shipping']['address'])) {
                $contact['address.shipping'] = $data['shipping']['address'];
            }
        }

        if (!empty($config['payment']['used'])) {
            $data['payment'] = $this->getPaymentData($config, $data);
        }

        return $data;
    }

    protected function getCustomerContact()
    {
        $customer_input_data = waRequest::request('customer');
        $selected_customer_mode = ifset($customer_input_data, 'is_company', false) ? shopCheckoutConfig::CUSTOMER_TYPE_COMPANY : shopCheckoutConfig::CUSTOMER_TYPE_PERSON;

        $config = $this->getCheckoutConfig();
        $auth_fields = $config->getAuthFields();
        if (!isset($auth_fields[$selected_customer_mode])) {
            $selected_customer_mode = key($auth_fields);
        }

        // Always new contact (API does not support auth yet)
        $contact = new waContact();
        $contact['is_company'] = $selected_customer_mode == shopCheckoutConfig::CUSTOMER_TYPE_COMPANY ? 1 : 0;
        if ($contact['is_company']) {
            $contact['company'] = ifset($customer_input_data, 'name', '');
        } else {
            $contact['name'] = ifset($customer_input_data, 'name', '');
        }
        if (!empty($customer_input_data['email'])) {
            $contact['email'] = $customer_input_data['email'];
        }
        if (!empty($customer_input_data['phone'])) {
            $contact['phone'] = $customer_input_data['phone'];
        }

        return $contact;
    }

    protected function getShippingData(shopCheckoutConfig $config, shopOrder $order)
    {
        $shipping_variant_id = waRequest::request('shipping_variant_id', null, 'string');
        if (!$shipping_variant_id) {
            return $this->getSimpleShippingData($config, $order);
        }
        $address = waRequest::request('address', null, waRequest::TYPE_ARRAY);
        if ($address && is_array($address)) {
            $address = array_filter($address, function($v) {
                return is_string($v) && strlen($v) > 0;
            });
        }
        if (!is_array($address) || empty($address['country']) || empty($address['region']) || empty($address['city'])) {
            throw new waAPIException('required_param', _w('When a “shipping_variant_id” value is provided then an address is also required with at least “country”, “region” and “city” fields’ values.'), 400);
        }

        list($shop_plugin_id, $internal_variant_id) = explode('.', $shipping_variant_id, 2) + [1 => ''];
        $plugin = $config->getShippingPluginByRate([
            'variant_id' => $shipping_variant_id,
        ]);
        if (!$plugin) {
            throw new waAPIException('bad_param', sprintf_wp('Unknown “%s” parameter value: %s.', 'shipping_variant_id', _w('plugin not found')), 400);
        }

        $required_address_fields = $plugin->requestedAddressFieldsForService($shipping_variant_id);
        $required_address_fields = ifempty($required_address_fields, []);
        $missing_field_ids = array_keys(array_diff_key($required_address_fields, $address));
        if ($missing_field_ids) {
            throw new waAPIException('required_param', sprintf_wp('Missing address fields required by the shipping plugin: %s.', join(', ', $missing_field_ids)), 400);
        }

        // Ask shipping plugin for final shipping rate, taking address data into account
        $order->discount;
        $is_free_shipping = ifempty($order, 'coupon', 'type', '') === '$FS';
        if ($order->shipping !== null) {
            if (!$is_free_shipping && $order->shipping == 0) {
                $is_free_shipping = true;
            }
        }

        $rates = $config->getShippingRates(
            $address,
            $order->items,
            ifset($order, 'contact', 'is_company', false) ? shopCheckoutConfig::CUSTOMER_TYPE_COMPANY : shopCheckoutConfig::CUSTOMER_TYPE_PERSON,
            [
                'shipping_params' => [],
                'id'              => $shop_plugin_id,
                'service'         => $shipping_variant_id,
            ]
        );

        $selected_variant = ifset($rates, $shipping_variant_id, null);
        if (!$selected_variant) {
            // Shipping plugin didn't return rate that user asked for.
            throw new waAPIException('bad_param', sprintf_wp('Unknown “%s” parameter value: %s.', 'shipping_variant_id', _w('failed to calculate the shipping cost')), 400);
        }

        // Apply free shipping coupon if set
        if ($is_free_shipping) {
            $selected_variant['rate'] = 0;
        } else {
            $plugin_model = new shopPluginModel();
            $plugin_info = $plugin_model->listPlugins('shipping', [
                'id' => $shop_plugin_id,
            ]);
            $plugin_info = reset($plugin_info);
            $min_rate = ifset($plugin_info, 'options', 'min_shipping_cost', 0);
            $min_rate = shop_currency($min_rate, null, $order['currency'], false);

            if (isset($selected_variant['error']) || !is_numeric($selected_variant['rate'])) {
                $selected_variant['rate'] = $min_rate;
            } else {
                $selected_variant['rate'] = shop_currency($selected_variant['rate'], $selected_variant['currency'], $order['currency'], false);
                if ($selected_variant['rate'] < $min_rate) {
                    $selected_variant['rate'] = $min_rate;
                }
            }
        }
        $selected_variant['currency'] = $order['currency'];
        unset($selected_variant['error']);

        $order['params'] += ['shipping_id' => $shipping_variant_id];
        $order['shipping'] = shop_currency($selected_variant['rate'], $selected_variant['currency'], $order['currency'], false);
        foreach ($address as $k => $v) {
            $order['params'] += ['shipping_address.' . $k => $v];
        }

        return [
            'address' => $address,
            'variant_id' => $shipping_variant_id,
            'selected_variant' => $selected_variant,
            'params' => [], // custom shipping plugin field values normally go here but not supported by headless API checkout as of yet
        ];
    }

    protected function getSimpleShippingData(shopCheckoutConfig $config, shopOrder $order)
    {
        $shop_plugin_id = waRequest::request('shipping_id', null, 'string');
        if (!$shop_plugin_id) {
            return null;
        }

        $plugin = $config->getShippingPluginByRate([
            'variant_id' => $shop_plugin_id,
        ]);
        if (!$plugin) {
            throw new waAPIException('bad_param', sprintf_wp('Unknown “%s” parameter value: %s.', 'shipping_id', _w('plugin not found')), 400);
        }

        $address = waRequest::request('address', null, waRequest::TYPE_ARRAY);
        if ($address && is_array($address)) {
            $address = array_filter($address, function($v) {
                return is_string($v) && strlen($v) > 0;
            });
        }

        // Ask shipping plugin for final shipping rate, taking address data into account
        $order->discount;
        $is_free_shipping = ifempty($order, 'coupon', 'type', '') === '$FS';
        if ($order->shipping !== null) {
            if (!$is_free_shipping && $order->shipping == 0) {
                $is_free_shipping = true;
            }
        }

        $rates = $config->getShippingRates(
            $address,
            $order->items,
            ifset($order, 'contact', 'is_company', false) ? shopCheckoutConfig::CUSTOMER_TYPE_COMPANY : shopCheckoutConfig::CUSTOMER_TYPE_PERSON,
            [
                'shipping_params' => [],
                'id'              => $shop_plugin_id,
            ]
        );

        $selected_variant = null;
        foreach ($rates as $rate) {
            if (isset($rate['error']) || !is_numeric($rate['rate'])) {
                $rate['currency'] = $order['currency'];
                unset($rate['error']);
                $rate['rate'] = 0;
            }
            $rate['rate'] = shop_currency($rate['rate'], $rate['currency'], $order['currency'], false);
            $rate['currency'] = $order['currency'];
            if ($selected_variant === null || $selected_variant['rate'] < $rate['rate']) {
                $selected_variant = $rate;
            }
        }

        if ($selected_variant && $is_free_shipping) {
            // Apply free shipping coupon if set
            $selected_variant['rate'] = 0;
        } else {
            $plugin_model = new shopPluginModel();
            $plugin_info = $plugin_model->listPlugins('shipping', [
                'id' => $shop_plugin_id,
            ]);
            $plugin_info = reset($plugin_info);

            if (!$selected_variant) {
                if (!$plugin_info) {
                    throw new waAPIException('bad_param', sprintf_wp('Unknown “%s” parameter value: %s.', 'shipping_id', _w('unable to use a shipping method')), 400);
                }
                // This is used when shipping method returned no shipping variants
                $selected_variant = [
                    'plugin' => $plugin->getId(),
                    'plugin_name' => $plugin_info['name'],
                    'logo' => ifset($plugin_info, 'logo', ''),
                    'icon' => ifset($plugin_info, 'info', 'icon', ''),
                    'img' => ifset($plugin_info, 'info', 'img', ''),
                    'name' => $plugin_info['name'],
                    'description' => $plugin_info['description'],
                    'rate' => 0,
                    'currency' => $order['currency'],
                    'variant_id' => $shop_plugin_id,
                ];
            }

            $min_rate = ifset($plugin_info, 'options', 'min_shipping_cost', 0);
            $min_rate = shop_currency($min_rate, null, $order['currency'], false);
            $selected_variant['rate'] = shop_currency($selected_variant['rate'], $selected_variant['currency'], $order['currency'], false);
            if ($selected_variant['rate'] < $min_rate) {
                $selected_variant['rate'] = $min_rate;
            }
        }

        $order['params'] += ['shipping_id' => $shop_plugin_id];
        $order['shipping'] = $selected_variant['rate'];
        foreach ($address as $k => $v) {
            $order['params'] += ['shipping_address.' . $k => $v];
        }

        return [
            'address' => $address,
            'variant_id' => $shop_plugin_id,
            'selected_variant' => $selected_variant,
            'params' => [],
        ];
    }

    protected function getPaymentData(shopCheckoutConfig $config, array $data)
    {
        $payment_id = waRequest::request('payment_id', null, 'int');
        if (!$payment_id) {
            return null;
        }

        $order = $data['order'];

        if (!empty($config['shipping']['used']) && !empty($order['params']['shipping_id'])) {
            // Filter based on shipping variant selected on previous step
            $selected_shipping_plugin_id = $order['params']['shipping_id'];
            $selected_shipping_type = $data['shipping']['selected_variant']['type'];

            $shipping_custom_data = ifset($data, 'shipping', 'selected_variant', 'custom_data', $selected_shipping_type, []);
            if (empty($shipping_custom_data) && ($selected_shipping_type === waShipping::TYPE_TODOOR)) {
                $shipping_custom_data = ifset($data, 'shipping', 'selected_variant', 'custom_data', 'courier', []);
            }

            $payment_type = ifset($shipping_custom_data, 'payment', []);
            $payment_type = array_unique(array_merge(array_keys(array_filter($payment_type)), $payment_type));
            $selected_shipping_payment_type = $payment_type ? $payment_type : null;
            if ($selected_shipping_payment_type !== null) {
                $known_payment_types = [
                    waShipping::PAYMENT_TYPE_CARD,
                    waShipping::PAYMENT_TYPE_CASH,
                    waShipping::PAYMENT_TYPE_PREPAID,
                ];
                $selected_shipping_payment_type = array_intersect($known_payment_types, $selected_shipping_payment_type);
            }
        } else {
            // Shipping is disabled in checkout settings.
            // Do not filter payment options based on selected shipping variant.
            $selected_shipping_plugin_id = null;
            $selected_shipping_type = null;
            $selected_shipping_payment_type = null;
        }

        $customer_type = $data['contact']['is_company'] ? shopCheckoutConfig::CUSTOMER_TYPE_COMPANY : shopCheckoutConfig::CUSTOMER_TYPE_PERSON;
        $order_has_frac = shopFrac::itemsHaveFractionalQuantity($data['order']->items);
        $order_has_units = shopUnits::itemsHaveCustomStockUnits($data['order']->items);
        $methods = $config->getPaymentRates(
            $selected_shipping_plugin_id,
            $customer_type,
            $selected_shipping_type,
            $selected_shipping_payment_type,
            $order_has_frac,
            $order_has_units
        );
        if (empty($methods)) {
            return null;
        }
        if (empty($methods[$payment_id])) {
            // User selected payment method that is not available
            //throw new waAPIException('bad_param', 'Unknown payment_id or not available with seleced shipping method', 400);
            return null; // allow to create order with no payment method selected
        }
        $order['params'] += ['payment_id' => $payment_id];
        return [
            'id' => $payment_id,
            'params' => [], // custom payment plugin field values normally go here but not supported by headless API checkout as of yet
        ];
    }
}
