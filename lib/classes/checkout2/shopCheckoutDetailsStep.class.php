<?php

/**
 * Fourth checkout step. Render address fields we didn't receive at Region step.
 * Render custom shipping plugin fields for shipping option selected by user on previous step.
 * Accept user input for all of those fields, validate.
 * Recalculate precise shipping rate, taking all input into account.
 */
class shopCheckoutDetailsStep extends shopCheckoutStep
{
    // Called before process() no matter if there was an error on previous checkout step
    public function prepare($data)
    {
        $result = [];

        // When error occured in previous step, process() will not run.
        // But we still need to do certain stuff.
        if (!empty($data['error_step_id'])) {
            // Render template
            $result = $this->addRenderedHtml([], $data, []);

        }

        // Remember user address in case they wrote it for different shipping type before
        $storage = $this->getCheckoutConfig()->getStorage();
        $stored_address = $storage->get('details_address');
        $stored_address = ifempty($stored_address, []);
        $input_values = ifset($data, 'input', 'details', 'shipping_address', []);
        $new_stored_address = array_filter($input_values) + $stored_address;
        if ($new_stored_address) {
            $storage->set('details_address', $new_stored_address);
        }

        return array(
            'result' => $result,
            'errors' => [],
            'can_continue' => true,
            'stored_address' => $stored_address,
            'new_stored_address' => $new_stored_address,
        );
    }

    /**
     * Called after prepare() only if there are no errors on previous checkout steps
     *
     * @param $data
     * @param $prepare_result
     * @return array
     * @throws waException
     */
    public function process($data, $prepare_result)
    {
        // Is shipping step disabled altogether?
        $config = $this->getCheckoutConfig();
        if (empty($config['shipping']['used'])) {
            $result = $this->addRenderedHtml([
                'disabled' => true,
            ], $data, []);
            return array(
                'result'       => $result,
                'errors'       => [],
                'can_continue' => true,
            );
        }

        // Paranoid checks...
        if (empty($data['shipping']['selected_variant']) || empty($data['shipping']['address']) || empty($data['contact'])) {
            return array(
                'data'         => $data,
                'result'       => $this->addRenderedHtml([], $data, []),
                'errors'       => array(),
                'can_continue' => false,
            );
        }

        // Previous checkout step - shipping - promises to save selected shipping variant into $data.
        $selected_variant = $data['shipping']['selected_variant'];
        list($shop_plugin_id, $internal_variant_id) = explode('.', $selected_variant['variant_id'], 2) + [1 => ''];

        // Instantiate proper shipping plugin
        $plugin = $config->getShippingPluginByRate($selected_variant);
        if (!$plugin) {
            $errors[] = [
                'id'      => 'general',
                'text'    => _w('Unknown shipping method.'),
                'section' => $this->getId(),
            ];
            return array(
                'data'         => $data,
                'result'       => $this->addRenderedHtml([], $data, $errors),
                'errors'       => $errors,
                'can_continue' => false,
            );
        }

        // Ask shipping plugin about required address fields
        $required_address_fields = $plugin->requestedAddressFieldsForService($selected_variant);
        $required_address_fields = ifempty($required_address_fields, []);

        // Checkout config gives shipping address fields, including those required by plugin
        $address_fields_config = $config->getAddressFields($required_address_fields);
        $address_fields_config = array_diff_key($address_fields_config, $data['shipping']['address']);

        // Fetch base values for shipping address from contact
        /** @var waContact $contact */
        $contact = $data['contact'];
        $base_values = $contact->get('address.shipping', 'js');
        if (!$base_values) {
            $base_values = $contact->get('address', 'js');
        }
        if (isset($base_values[0])) {
            $base_values = $base_values[0];
        }
        $base_values = ifset($base_values, 'data', []);
        foreach ($address_fields_config as $field_id => $field_info) {
            $base_value = ifset($base_values, $field_id, '');
            if (is_array($base_value)) {
                $base_value = '';
            }
            $base_values[$field_id] = (string)$base_value;
        }

        // Customer-supplied values from POST for shipping address
        $input_values = ifset($data, 'input', 'details', 'shipping_address', []);

        // Check if user just logged in. If so, put missing data into fields
        // despite there being (empty) values in old input
        $user_id_from_input = ifset($data, 'input', 'auth', 'user_id', '');
        if ($base_values && $user_id_from_input != $contact['id']) {
            $input_values = array_filter($input_values);
        }

        // If user previously supplied their address for a different shipping type, load it into empty fields, too
        $base_values = $prepare_result['stored_address'] + $base_values;

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
                    $errors[] = [
                        'name'    => "details[shipping_address][{$field_id}]",
                        'text'    => _w('This field is required.'),
                        'section' => $this->getId(),
                    ];
                } else {
                    $delayed_errors["details[shipping_address][{$field_id}]"] = _w('This field is required.');
                }
            }
        }
        unset($field_info);

        // Ask shipping plugin for custom fields required for exact rate calculation

        /**
         * добавляем дату и время, когда магазин приготовит заказ и учитываем
         * время ($assembly_time) на комплектацию заказа указанным способом доставки
         */
        $assembly_time  = $this->getCheckoutConfig()->getAssemblyTimeByRate($selected_variant);
        $departure_date = shopDepartureDateTimeFacade::getDeparture($this->getCheckoutConfig()['schedule']);
        $departure_date->setExtraProcessingTime((int) $assembly_time * 3600);
        $departure_datetime = (string) $departure_date->getDepartureDateTime();

        /** отправляем в плагин временную зону магазина */
        $shop_config = wa('shop')->getConfig()->getSchedule();

        $custom_input_values = ifset($data, 'input', 'details', 'custom', []);
        $plugin_custom_field_settings = $plugin->customFieldsForService(new waOrder([
            'contact_id'       => ifempty($contact, 'id', null),
            'contact'          => $contact,
            'items'            => $data['order']['items'],
            'shipping_address' => $address,
            'shipping_params'  => $custom_input_values,
            'params'          => [
                'departure_datetime' => $departure_datetime,
                'shop_time_zone'     => ifset($shop_config['timezone'], date_default_timezone_get())
            ],
        ]), $selected_variant);

        // Render custom plugin fields. Also gather their values validated by plugin.
        $custom_field_values = [];
        $plugin_custom_fields = [];

        foreach ($plugin_custom_field_settings as $field_id => $row) {
            if (isset($row['errors'])) {
                foreach ($row['errors'] as $field_name => $error_message) {
                    $errors[] = [
                        'name'    => "details[custom][{$field_name}]",
                        'text'    => $error_message,
                        'section' => $this->getId(),
                    ];
                }
            }
            if (array_key_exists('value', $row)) {
                if (isset($row['value'])) { // null means do not pass to calculate()
                    $custom_field_values[$field_id] = $row['value'];
                }
            } elseif (isset($custom_input_values[$field_id])) {
                $custom_field_values[$field_id] = $custom_input_values[$field_id];
            }
            if (isset($selected_variant['delivery_date'])) {
                if (is_array($selected_variant['delivery_date'])) {
                    $row['delivery_date'] = strtotime(reset($selected_variant['delivery_date']));
                } else {
                    $row['delivery_date'] = strtotime($selected_variant['delivery_date']);
                }
            }

            $plugin_custom_fields[$field_id] = $this->renderWaHtmlControl($field_id, $row, $data['origin'] != 'create' ? 'details[custom]' : false);
            $plugin_custom_fields[$field_id]['affects_rate'] = !empty($row['data']['affects-rate']);
        }

        // Ask shipping plugin for final shipping rate, taking all the custom and address data into account
        $updated_selected_variant = null;
        if (!$errors) {
            $payment = ifset($data, 'input', 'payment', []);
            if (!empty($payment['id'])) {
                $payment += shopPayment::getPluginInfo($payment['id']);
            }
            $rates = $config->getShippingRates(
                $address,
                $data['shipping']['items'],
                $contact['is_company'] ? shopCheckoutConfig::CUSTOMER_TYPE_COMPANY : shopCheckoutConfig::CUSTOMER_TYPE_PERSON,
                [
                    'shipping_params' => $custom_field_values,
                    'id'              => $shop_plugin_id,
                    'service'         => $selected_variant,
                    'payment_type'    => array_keys(ifset($payment, 'options', 'payment_type', [])),
                ]
            );

            if (isset($rates[$selected_variant['variant_id']]['error'])) {
                // Shipping plugin returned an error
                $errors[] = [
                    'id'      => 'details_plugin',
                    'text'    => $rates[$selected_variant['variant_id']]['error'],
                    'section' => $this->getId(),
                ];
            } elseif (isset($rates[$selected_variant['variant_id']])) {
                // Shipping plugin returned proper rate
                $currencies = $config->getCurrencies();
                $updated_selected_variant = shopCheckoutShippingStep::prepareShippingVariant($rates[$selected_variant['variant_id']], $currencies);
                if (!empty($updated_selected_variant['custom_data']['pickup']['schedule'])) {
                    if (is_array($updated_selected_variant['custom_data']['pickup']['schedule'])) {
                        $updated_selected_variant_timezone = ifset($updated_selected_variant, 'custom_data', 'pickup', 'timezone', null);
                        $updated_selected_variant['pickup_schedule'] = shopCheckoutShippingStep::formatPickupSchedule(
                            $updated_selected_variant['custom_data']['pickup']['schedule'],
                            waDateTime::getTimeZones(),
                            $config['schedule']['timezone'],
                            $updated_selected_variant_timezone
                        );
                        $updated_selected_variant['pickup_schedule']['user_timezone'] = $contact->getTimezone();
                    } else {
                        $updated_selected_variant['pickup_schedule_html'] = $updated_selected_variant['custom_data']['pickup']['schedule'];
                    }
                }

                if (is_numeric($updated_selected_variant['rate'])) {
                    $is_free_shipping = ifempty($data, 'shipping', 'is_free_shipping', false);
                    if ($is_free_shipping) {
                        $updated_selected_variant['rate'] = 0;
                    }
                    $data['order']['shipping'] = shop_currency($updated_selected_variant['rate'], $selected_variant['currency'], $data['order']['currency'], false);
                } else {
                    // Shipping plugin didn't return a single final rate.
                    // This is exceptional. This should not happen.
                    $errors[] = [
                        'id'      => 'shipping',
                        'text'    => _w('Unable to calculate a shipping rate.'),
                        'section' => $this->getId(),
                    ];
                }
            } else {
                // Shipping plugin didn't return rate that user asked for.
                // This is exceptional. This should not happen.
                $errors[] = [
                    'id'      => 'shipping',
                    'text'    => _w('Unable to calculate a shipping rate.'),
                    'section' => $this->getId(),
                ];
            }

            if (!$errors) {
                $data['shipping']['id'] = $shop_plugin_id;
                $data['shipping']['address'] = $address;

                // re-format shipping params flattening arrays
                $data['shipping']['params'] = [];
                foreach ($custom_field_values as $k => $v) {
                    if (is_array($v)) {
                        foreach ($v as $kk => $vv) {
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

        $adapter = null;
        $apikey = null;
        try {
            $adapter = wa()->getMap()->getId();
            if ($adapter === 'yandex') {
                $apikey = 'apikey';
            } else {
                $apikey = 'key';
            }
            $apikey = wa()->getMap()->getSettings($apikey);
        } catch (Exception $e) {

        }

        $map = [
            'adapter' => $adapter,
            'api_key' => $apikey
        ];

        return array(
            'data'         => $data,
            'result'       => $this->addRenderedHtml([
                'shipping_address_fields'       => $address_fields,
                'shipping_address_fields_order' => array_keys($address_fields),
                'plugin_custom_fields'          => $plugin_custom_fields,
                'plugin_custom_fields_order'    => array_keys($plugin_custom_fields),
                'preliminary_shipping_rate'     => $selected_variant,
                'shipping_rate'                 => $updated_selected_variant,
                'map'                           => $map,
            ], $data, $errors),
            'errors'       => $errors,
            'can_continue' => true,
        );
    }

    public function getTemplatePath()
    {
        return 'details.html';
    }
}
