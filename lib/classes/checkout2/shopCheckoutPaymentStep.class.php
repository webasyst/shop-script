<?php
/**
 * Fifth checkout step. Determine available payment options
 * and accept user choice among them.
 */
class shopCheckoutPaymentStep extends shopCheckoutStep
{
    public function process($data, $prepare_result)
    {
        // Is payment step disabled altogether?
        $config = $this->getCheckoutConfig();
        if (empty($config['payment']['used'])) {
            return array(
                'result' => $this->addRenderedHtml([
                    'disabled' => true,
                ], $data, []),
                'errors' => [],
                'can_continue' => true,
            );
        }

        if (!empty($config['shipping']['used']) && !empty($data['shipping']['selected_variant'])) {
            // Filter based on shipping variant selected on previous step
            $selected_shipping_plugin_id = explode('.', $data['shipping']['selected_variant']['variant_id'], 2)[0];
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

        // List of available payment options
        /** @var waContact $contact */
        $contact = $data['contact'];
        $customer_type = $contact['is_company'] ? shopCheckoutConfig::CUSTOMER_TYPE_COMPANY : shopCheckoutConfig::CUSTOMER_TYPE_PERSON;
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

        // Currently selected payment option
        $selected_method_id = ifset($data, 'input', 'payment', 'id', null);
        // Payment custom field values for selected payment option
        $payment_custom_field_values = ifset($data, 'input', 'payment', 'custom', []);

        // Fetch custom fields from each plugin
        foreach ($methods as $key => &$m) {
            try {
                /** @var waPayment $plugin */
                $plugin = $m['__instance'];
                $plugin_info = $m['__plugin_info'];
                unset($m['__instance'], $m['__plugin_info']);

                // Pass values of custom fields to plugin that rendered them
                $wa_order_params = [];
                if ($selected_method_id && $selected_method_id == $m['id']) {
                    foreach ($payment_custom_field_values as $k => $v) {
                        $wa_order_params['payment_params_'.$k] = $v;
                    }
                }

                // Ask plugin for its custom fields, passing in values to selected plugin.
                // Plugin's responsibility to validate user's data and provide proper values
                // for each field in result it returns.
                $m['custom_fields'] = $plugin->customFields(new waOrder([
                    'contact'    => $contact,
                    'contact_id' => $contact->getId(),
                    'params'     => $wa_order_params,
                ]));

                if (empty($m['custom_fields']) || !is_array($m['custom_fields'])) {
                    $m['custom_fields'] = [];
                } else {
                    // Render custom fields of payment plugin into HTML
                    foreach ($m['custom_fields'] as $field_id => $row) {
                        if (!isset($row['value'])) {
                            $row['value'] = ifset($payment_custom_field_values, $field_id, '');
                        }
                        $m['custom_fields'][$field_id] = $this->renderWaHtmlControl($field_id, $row, $data['origin'] != 'create' ? 'payment[custom]' : false);
                    }
                }

                $m['custom_fields_order'] = array_keys($m['custom_fields']);

            } catch (waException $ex) {
                waLog::log($ex->getMessage(), 'shop/checkout.error.log');
                unset($methods[$key]);
            }
        }
        unset($m);

        if (1 == count($methods) && !$selected_method_id) {
            $selected_method_id = array_keys($methods)[0];
        }

        $errors = [];
        $custom_field_values_validated = [];
        if (empty($methods)) {
            // Well... it's not an error I guess?..
        } elseif (!$selected_method_id) {
            // User didn't select payment method
            $errors[] = [
                'name' => 'payment[id]',
                'text' => _w('Please select a payment method'),
                'section' => $this->getId(),
            ];
        } elseif (empty($methods[$selected_method_id])) {
            // User selected payment method that does not exist.
            // This should never happen.
            $errors[] = [
                'name' => 'payment[id]',
                'text' => _w('Please select a payment method'),
                'section' => $this->getId(),
            ];
        } elseif (!empty($methods[$selected_method_id]['custom_fields'])) {
            // Check for required fields, as well as errors returned by plugin
            foreach ($methods[$selected_method_id]['custom_fields'] as $field_id => $row) {
                if (isset($row['error'])) {
                    $errors[] = [
                        'name' => 'payment[custom]['.$field_id.']',
                        'text' => $row['error'],
                        'section' => $this->getId(),
                    ];
                } elseif (!empty($row['required']) && !strlen(ifset($row, 'value', ''))) {
                    $errors[] = [
                        'name' => 'payment[custom]['.$field_id.']',
                        'text' => _w('This field is required.'),
                        'section' => $this->getId(),
                    ];
                } else {
                    $custom_field_values_validated[$field_id] = ifset($row, 'value', '');
                }
            }
        }

        if (!$errors) {
            $data['payment'] = [
                'id' => $selected_method_id,
                'params' => $custom_field_values_validated,
            ];
        }

        $result = $this->addRenderedHtml([
            'selected_method_id' => $selected_method_id,
            'methods' => $methods,
        ], $data, $errors);

        if ($data['origin'] !== 'form' && 'only' === ifset($data, 'input', 'payment', 'html', null)) {
            unset($result['methods']);
        }

        return array(
            'data' => $data,
            'result' => $result,
            'errors' => $errors,
            'can_continue' => !$errors,
        );
    }

    public function getTemplatePath()
    {
        return 'payment.html';
    }
}
