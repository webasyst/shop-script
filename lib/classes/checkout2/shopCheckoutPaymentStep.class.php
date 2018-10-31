<?php
/**
 * Fifth checkout step. Determine available payment options
 * and accept user choice among them.
 */
class shopCheckoutPaymentStep extends shopCheckoutStep
{
    public function process($data, $prepare_result)
    {
        // Shipping option selected on previous step
        $selected_variant = $data['shipping']['selected_variant'];
        $shop_plugin_id = explode('.', $selected_variant['variant_id'], 2)[0];

        // Currently selected payment option
        $selected_method_id = ifset($data, 'input', 'payment', 'id', null);
        // Payment custom field values for selected payment option
        $payment_custom_field_values = ifset($data, 'input', 'payment', 'custom', []);

        // List of available payment options
        $config = $this->getCheckoutConfig();
        /** @var waContact $contact */
        $contact = $data['contact'];
        $customer_type = $contact['is_company'] ? shopCheckoutConfig::CUSTOMER_TYPE_COMPANY : shopCheckoutConfig::CUSTOMER_TYPE_PERSON;
        $methods = $config->getPaymentRates($shop_plugin_id, $customer_type, $selected_variant['type']);

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

        $errors = [];
        $custom_field_values_validated = [];
        if (empty($methods)) {
            // Well... it's not an error I guess?..
        } elseif (!$selected_method_id) {
            // User didn't select payment method
            $errors = [
                'payment[id]' => _w('Please select payment method'),
            ];
        } elseif (empty($methods[$selected_method_id])) {
            // User selected payment method that does not exist.
            // This should never happen.
            $errors = [
                'payment[id]' => _w('Please select payment method'),
            ];
        } elseif (!empty($methods[$selected_method_id]['custom_fields'])) {
            // Check for required fields, as well as errors returned by plugin
            foreach ($methods[$selected_method_id]['custom_fields'] as $field_id => $row) {
                $key = 'payment[custom]['.$field_id.']';
                if (isset($row['error'])) {
                    $errors[$key] = $row['error'];
                } elseif (!empty($row['required']) && !strlen(ifset($row, 'value', ''))) {
                    $errors[$key] = _w('This field is required.');
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
        return wa()->getAppPath('templates/actions/frontend/order/form/payment.html', 'shop');
    }
}
