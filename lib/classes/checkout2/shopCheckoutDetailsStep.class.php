<?php
/**
 * Fourth checkout step. Render address fields we didn't receive at Region step.
 * Render custom shipping plugin fields for shipping option selected by user on previous step.
 * Accept user input for all of those fields, validate.
 * Recalculate precise shipping rate, taking all input into account.
 */
class shopCheckoutDetailsStep extends shopCheckoutStep
{
    public function process($data, $prepare_result)
    {
        // Paranoid checks...
        if (empty($data['shipping']['selected_variant']) || empty($data['shipping']['address']) || empty($data['contact'])) {
            return array(
                'data' => $data,
                'result' => $this->addRenderedHtml([], $data, []),
                'errors' => array(),
                'can_continue' => false,
            );
        }

        // Previous checkout step - shipping - promises to save selected shipping variant into $data.
        $selected_variant = $data['shipping']['selected_variant'];
        list($shop_plugin_id, $internal_variant_id) = explode('.', $selected_variant['variant_id'], 2) + [1=>''];

        // Instantiate proper shipping plugin
        $config = $this->getCheckoutConfig();
        $plugin = $config->getShippingPluginByRate($selected_variant);
        if (!$plugin) {
            $errors = ['general' => _w('Unknown shipping method.')];
            return array(
                'data' => $data,
                'result' => $this->addRenderedHtml([], $data, $errors),
                'errors' => $errors,
                'can_continue' => false,
            );
        }

        // Ask shipping plugin about required address fields
        $required_address_fields = $plugin->requestedAddressFieldsForService($selected_variant);

        // Checkout config gives shipping address fields, including those required by plugin
        $address_fields_config = $config->getAddressFields($required_address_fields);
        $address_fields_config = array_diff_key($address_fields_config, $data['shipping']['address']);

        // Fetch base values for shipping address from contact
        /** @var waContact $contact */
        $contact = $data['contact'];
        $base_values = $contact->get('address', 'js');
        if (isset($base_values[0])) {
            $base_values = $base_values[0];
        }
        $base_values = ifset($base_values, 'data', []);
        foreach ($address_fields_config as $field_id => $field_info) {
            $base_value = ifset($base_values, $field_id, '');
            if (is_array($base_value)) {
                $base_value = '';
            }
            $base_values[$field_id] = (string) $base_value;
        }

        // Customer-supplied values from POST for shipping address
        $input_values = ifset($data, 'input', 'details', 'shipping_address', []);

        // Format shipping address fields for template
        $address_fields = $config->formatContactFields($address_fields_config, $input_values, $base_values);

        // Prepare shipping address data with all the fields this time. Validate required fields.
        $errors = [];
        $delayed_errors = [];
        $address = $data['shipping']['address'];
        foreach ($address_fields as $field_id => &$field_info) {
            $field_info['name'] = 'details[shipping_address]['.$field_info['id'].']';
            $address[$field_id] = ifset($field_info, 'value', '');
            $field_info['affects_rate'] = !empty($required_address_fields[$field_id]['cost']);
            if (!empty($field_info['required']) && !strlen($address[$field_id])) {
                if ($field_info['affects_rate']) {
                    $errors["details[shipping_address][{$field_id}]"] = _w('This field is required.');
                } else {
                    $delayed_errors["details[shipping_address][{$field_id}]"] = _w('This field is required.');
                }
            }
        }
        unset($field_info);

        // Ask shipping plugin for custom fields required for exact rate calculation
        $custom_input_values = ifset($data, 'input', 'details', 'custom', []);
        $plugin_custom_field_settings = $plugin->customFieldsForService(new waOrder([
            'contact_id'       => ifempty($contact, 'id', null),
            'contact'          => $contact,
            'items'            => $data['order']['items'],
            'shipping_address' => $address,
            'shipping_params'  => $custom_input_values,
        ]), $selected_variant);

        // Render custom plugin fields. Also gather their values validated by plugin.
        $custom_field_values = [];
        $plugin_custom_fields = [];
        foreach ($plugin_custom_field_settings as $field_id => $row) {
            if (array_key_exists('value', $row)) {
                if (isset($row['value'])) { // null means do not pass to calculate()
                    $custom_field_values[$field_id] = $row['value'];
                }
            } elseif (isset($custom_input_values[$field_id])) {
                $custom_field_values[$field_id] = $custom_input_values[$field_id];
            }
            $plugin_custom_fields[$field_id] = $this->renderWaHtmlControl($field_id, $row, $data['origin'] != 'create' ? 'details[custom]' : false);
            $plugin_custom_fields[$field_id]['affects_rate'] = !empty($row['data']['affects-rate']);
        }

        // Ask shipping plugin for final shipping rate, taking all the custom and address data into account
        $updated_selected_variant = null;
        if (!$errors) {

            $rates = $config->getShippingRates(
                $address,
                $data['order']['items'],
                $contact['is_company'] ? shopCheckoutConfig::CUSTOMER_TYPE_COMPANY : shopCheckoutConfig::CUSTOMER_TYPE_PERSON,
                [
                    'shipping_params' => $custom_field_values,
                    'id'              => $shop_plugin_id,
                ]
            );

            if (isset($rates[$selected_variant['variant_id']]['error'])) {
                // Shipping plugin returned an error
                $errors['shipping'] = $rates[$selected_variant['variant_id']]['error'];
            } elseif (isset($rates[$selected_variant['variant_id']])) {
                // Shipping plugin returned proper rate
                $currencies = $config->getCurrencies();
                $updated_selected_variant = shopCheckoutShippingStep::prepareShippingVariant($rates[$selected_variant['variant_id']], $currencies);
                if (!empty($updated_selected_variant['custom_data']['pickup']['schedule']) && is_array($updated_selected_variant['custom_data']['pickup']['schedule'])) {
                    $updated_selected_variant_timezone = ifset($updated_selected_variant, 'custom_data', 'pickup', 'timezone', null);
                    $updated_selected_variant['pickup_schedule'] = shopCheckoutShippingStep::formatPickupSchedule(
                        $updated_selected_variant['custom_data']['pickup']['schedule'],
                        waDateTime::getTimeZones(), $config['schedule']['timezone'],
                        $updated_selected_variant_timezone
                    );
                }

                if (is_numeric($updated_selected_variant['rate'])) {
                    $data['order']['shipping'] = shop_currency($updated_selected_variant['rate'], $selected_variant['currency'], $data['order']['currency'], false);
                } else {
                    // Shipping plugin didn't return a single final rate.
                    // This is exceptional. This should not happen.
                    $errors['shipping'] = _w('Unable to calculate shipping rate.');
                }
            } else {
                // Shipping plugin didn't return rate that user asked for.
                // This is exceptional. This should not happen.
                $errors['shipping'] = _w('Unable to calculate shipping rate.');
            }

            if (!$errors) {
                $data['shipping']['id'] = $shop_plugin_id;
                $data['shipping']['address'] = $address;

                // re-format shipping params flattening arrays
                $data['shipping']['params'] = [];
                foreach($custom_field_values as $k => $v) {
                    if (is_array($v)) {
                        foreach($v as $kk => $vv) {
                            $data['shipping']['params'][$k.'.'.$kk] = $vv;
                        }
                    } else {
                        $data['shipping']['params'][$k] = $v;
                    }
                }
            }
        }

        if ($delayed_errors) {
            $data['details']['delayed_errors'] = $delayed_errors;
        }

        return array(
            'data' => $data,
            'result' => $this->addRenderedHtml([
                'shipping_address_fields' => $address_fields,
                'shipping_address_fields_order' => array_keys($address_fields),
                'plugin_custom_fields' => $plugin_custom_fields,
                'plugin_custom_fields_order' => array_keys($plugin_custom_fields),
                'preliminary_shipping_rate' => $selected_variant,
                'shipping_rate' => $updated_selected_variant,
            ], $data, $errors),
            'errors' => $errors,
            'can_continue' => true,
        );
    }

    public function getTemplatePath()
    {
        return wa()->getAppPath('templates/actions/frontend/order/form/details.html', 'shop');
    }
}
