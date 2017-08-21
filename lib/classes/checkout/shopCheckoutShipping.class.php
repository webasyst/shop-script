<?php

class shopCheckoutShipping extends shopCheckout
{
    protected $step_id = 'shipping';

    public function display()
    {
        $plugin_model = new shopPluginModel();
        $methods = $plugin_model->listPlugins('shipping');
        if (waRequest::param('shipping_id') && is_array(waRequest::param('shipping_id'))) {
            foreach ($methods as $m_id => $m) {
                if (!in_array($m_id, waRequest::param('shipping_id'))) {
                    unset($methods[$m_id]);
                }
            }
        }
        $address = $this->getAddress();
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
        $items = $this->getItems();

        $cart = new shopCart();
        $total = $cart->total();

        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */


        $settings = $config->getCheckoutSettings();
        $address_form = !isset($settings['contactinfo']) || !isset($settings['contactinfo']['fields']['address.shipping']);
        if (!isset($settings['contactinfo']) ||
            (!isset($settings['contactinfo']['fields']['address.shipping']) && !isset($settings['contactinfo']['fields']['address']))) {
            $settings = $config->getCheckoutSettings(true);
        }
        if (!$address) {
            $shipping_address = array();
            $address_form = true;
            if (!empty($settings['contactinfo']['fields']['address'])) {
                foreach ($settings['contactinfo']['fields']['address']['fields'] as $k => $f) {
                    if (!empty($f['value'])) {
                        $shipping_address[$k] = $f['value'];
                    }
                }
            }
        } else {
            $shipping_address = $address;
        }

        if (waRequest::method() == 'post') {
            $shipping_id = waRequest::post('shipping_id');
            $rate_id = waRequest::post('rate_id');
            $selected_shipping = array(
                'id' => $shipping_id,
                'rate_id' => isset($rate_id[$shipping_id]) ? $rate_id[$shipping_id] : ''
            );
        } else {
            $selected_shipping = $this->getSessionData('shipping', array());
        }

        $currencies = $config->getCurrencies();
        $current_currency =$config->getCurrency(false);
        foreach ($methods as $method_id => $m) {
            try {
                $plugin = shopShipping::getPlugin($m['plugin'], $m['id']);
                $plugin_info = $plugin->info($m['plugin']);
                $m['icon'] = $plugin_info['icon'];
                $m['img'] = $plugin_info['img'];
                $m['currency'] = $plugin->allowedCurrency();
                $m['external'] = ($selected_shipping && $selected_shipping['id'] == $m['id']) ? 0 : $plugin->getProperties('external');

                if ($plugin->isAllowedAddress($shipping_address)) {
                    if ($m['external']) {
                        $m['rates'] = array();
                    } else {
                        $options = array(
                            'currency' => $m['currency'],
                            'weight'   => $plugin->allowedWeightUnit(),
                        );
                        $total_price = shopHelper::workupValue($total, 'price', $options['currency']);

                        $shipping_items = shopHelper::workupOrderItems($items, $options);
                        $m['rates'] = $plugin->getRates($shipping_items, $shipping_address, array('total_price' => $total_price));
                    }
                } else {
                    $m['rates'] = false;
                }

                if (is_array($m['rates'])) {
                    if (!isset($currencies[$m['currency']])) {
                        $m['rate'] = 0;
                        $m['error'] = sprintf(_w('Shipping rate was not calculated because required currency %s is not defined in your store settings.'), $m['currency']);
                        $methods[$method_id] = $m;
                        continue;
                    }
                    foreach ($m['rates'] as &$r) {
                        if (is_array($r['rate'])) {
                            $r['rate'] = max($r['rate']);
                        }

                        // Apply rounding. This converts all rates to current frontend currency.
                        if ($r['rate'] && wa()->getSetting('round_shipping')) {
                            $r['rate'] = shopRounding::roundCurrency(shop_currency($r['rate'], $m['currency'], $current_currency, false), $current_currency);
                            $r['currency'] = $current_currency;
                        }
                    }
                    unset($r);
                    if (wa()->getSetting('round_shipping')) {
                        $m['currency'] = $current_currency;
                    }
                    if ($m['rates']) {
                        if (!empty($selected_shipping['rate_id']) && isset($m['rates'][$selected_shipping['rate_id']])) {
                            $rate = $m['rates'][$selected_shipping['rate_id']];
                        } else {
                            $rate = reset($m['rates']);
                        }
                        $m['rate'] = $rate['rate'];
                        $m['est_delivery'] = isset($rate['est_delivery']) ? $rate['est_delivery'] : '';
                        if (!empty($rate['comment'])) {
                            $m['comment'] = $rate['comment'];
                        }
                    } else {
                        $m['rates'] = array();
                        $m['rate'] = null;
                    }
                } elseif (is_string($m['rates'])) {
                    if ($address) {
                        $m['error'] = $m['rates'];
                    } else {
                        $m['rates'] = array();
                        $m['rate'] = null;
                    }
                } else {
                    unset($methods[$method_id]);
                    continue;
                }

                // When free shipping coupon is used, display all rates as 0
                $checkout_data = wa('shop')->getStorage()->read('shop/checkout');
                if (!empty($checkout_data['coupon_code'])) {
                    empty($cm) && ($cm = new shopCouponModel());
                    $coupon = $cm->getByField('code', $checkout_data['coupon_code']);
                    if ($coupon && $coupon['type'] == '$FS') {
                        $m['rate'] = 0;
                        foreach ($m['rates'] as &$r) {
                            $r['rate'] = 0;
                        }
                        unset($r);
                    }
                }

                $custom_fields = $this->getCustomFields($m['id'], $plugin);
                $custom_html = '';
                foreach ($custom_fields as $c) {
                    $custom_html .= '<div class="wa-field">'.$c.'</div>';
                }
                if ($custom_html) {
                    $m['custom_html'] = $custom_html;
                }

                $f = $this->getAddressForm($m['id'], $plugin, $settings, $address, $address_form);
                if ($f) {
                    $m['form'] = $f;
                    $m['form']->setValue($this->getContact());
                    // Make sure there are no more than one address of each type in the form
                    foreach (array('address.shipping') as $fld) {
                        if (isset($m['form']->values[$fld]) && count($m['form']->values[$fld]) > 1) {
                            $m['form']->values[$fld] = array(reset($m['form']->values[$fld]));
                        }
                    }
                }

                $methods[$method_id] = $m;
            } catch (waException $ex) {
                waLog::log($ex->getMessage(), 'shop/checkout.error.log');
            }
        }

        $view = wa()->getView();
        $view->assign('checkout_shipping_methods', $methods);
        $default_method = '';
        foreach ($methods as $m) {
            if (empty($m['error'])) {
                $default_method = $m['id'];
                break;
            }
        }
        $view->assign('shipping', $selected_shipping ? $selected_shipping : array('id' => $default_method, 'rate_id' => ''));

        $checkout_flow = new shopCheckoutFlowModel();
        $step_number = shopCheckout::getStepNumber('shipping');
        // IF no errors
        $checkout_flow->add(array(
            'step' => $step_number
        ));
        // ELSE
//        $checkout_flow->add(array(
//            'step' => $step_number,
//            'description' => ERROR MESSAGE HERE
//        ));

    }

    public function getAddressForm($method_id, waShipping $plugin, $config, $contact_address, $address_form)
    {
        $config_address = isset($config['contactinfo']['fields']['address.shipping']) ?
            $config['contactinfo']['fields']['address.shipping'] :
            (isset($config['contactinfo']['fields']['address']) ? $config['contactinfo']['fields']['address'] : array());

        $address_fields = $plugin->requestedAddressFields();
        $disabled_only = $address_fields === array() ? false: true;
        if ($address_fields === false || $address_fields === null) {
            return false;
        }
        foreach ($address_fields as $f) {
            if ($f !== false) {
                $disabled_only = false;
                break;
            }
        }
        $address = array();
        if ($disabled_only) {
            $allowed = $plugin->allowedAddress();
            if (count($allowed) == 1) {
                $one = true;
                if (!isset($config_address['fields'])) {
                    $fields = array();
                    $address_field = waContactFields::get('address');
                    foreach ($address_field->getFields() as $f) {
                        /**
                         * @var waContactAddressField $f
                         */
                        $fields[$f->getId()] = array();
                    }
                } else {
                    $fields =  $config_address['fields'];
                }
                foreach ($allowed[0] as $k => $v) {
                    if (is_array($v)) {
                        $one = false;
                        break;
                    } else {
                        $fields[$k]['hidden'] = 1;
                        $fields[$k]['value'] = $v;
                    }
                }
                foreach ($address_fields as $k => $v) {
                    if ($v === false && isset($fields[$k])) {
                        unset($fields[$k]);
                    }
                }
                if ($one) {
                    $address = $config_address;
                    $address['fields'] = $fields;
                }
            } else {

            }
        } else {
            $union = false;
            if (isset($config_address['fields'])) {
                $fields = $config_address['fields'];
                if ($address_fields) {

                    foreach ($fields as $f_id => $f) {
                        if (isset($address_fields[$f_id])) {
                            if (is_array($address_fields[$f_id])) {
                                foreach ($address_fields[$f_id] as $k => $v) {
                                    $fields[$f_id][$k] = $v;
                                }
                            } elseif ($address_fields[$f_id] === false) {
                                unset($fields[$f_id]);
                                unset($address_fields[$f_id]);
                            }
                        } elseif (!$union) {
                            unset($fields[$f_id]);
                        }
                    }
                    foreach ($address_fields as $f_id => $f) {
                        if (!isset($fields[$f_id])) {
                            $fields[$f_id] = $f === false ? array() : $f;
                        }
                    }

                }
                $address_fields = $fields;
            }
            if ($address_fields) {
                $address = array('fields' => $address_fields);
            }
        }

        if (!$address_form && !empty($address['fields'])) {
            foreach ($address['fields'] as $k => $v) {
                if (empty($contact_address[$k])) {
                    $address_form = true;
                    break;
                }
            }
        }

        if ($address_form) {
            if (!empty($config['shipping']['prompt_type'])) {
                #show only cost type fields
                if (!empty($address['fields'])) {
                    foreach ($address['fields'] as $k => $v) {
                        if (empty($v['cost'])) {
                            unset($address['fields'][$k]);
                        }
                    }
                    if (!$address['fields']) {
                        return null;
                    }
                } else {
                    $empty = true;
                    foreach ($address_fields as $f) {
                        if (!empty($f['cost'])) {
                            $empty = false;
                            break;
                        }
                    }
                    if ($empty) {
                        return null;
                    }
                }
            }

            #attempt to sort address fields
            if (!empty($address['fields']) && !empty($config_address['fields'])) {
                $sort = array_flip(array_keys($config_address['fields']));
                $code = ' $map = '.var_export($sort, true).';';
                $code .= ' return ifset($map[$a],0)-ifset($map[$b],0);';

                $compare = create_function('$a, $b', $code);
                uksort($address['fields'], $compare);
            }

            return waContactForm::loadConfig(array('address.shipping' => $address), array('namespace' => 'customer_'.$method_id));
        } else {
            return null;
        }
    }



    public function getItems($weight_unit = null)
    {
        $items = array();
        $cart = new shopCart();
        $cart_items = $cart->items();
        $product_ids = $sku_ids = array();
        foreach ($cart_items as $item) {
            $product_ids[] = $item['product_id'];
            $sku_ids[] = $item['sku_id'];
        }
        $feature_model = new shopFeatureModel();
        $f = $feature_model->getByCode('weight');
        if (!$f) {
            $values = array();
        } else {
            $values_model = $feature_model->getValuesModel($f['type']);
            $values = $values_model->getProductValues($product_ids, $f['id']);
        }

        $m = null;
        if ($weight_unit) {
            $dimension = shopDimension::getInstance()->getDimension('weight');
            if ($weight_unit != $dimension['base_unit']) {
                $m = $dimension['units'][$weight_unit]['multiplier'];
            }
        }

        foreach ($cart_items as $item) {
            if (isset($values['skus'][$item['sku_id']])) {
                $w = $values['skus'][$item['sku_id']];
            } else {
                $w = isset($values[$item['product_id']]) ? $values[$item['product_id']] : 0;
            }
            if ($m !== null) {
                $w = $w / $m;
            }
            $items[] = array(
                'name' => $item['name'],
                'price' => $item['price'],
                'currency' => $item['currency'],
                'quantity' => $item['quantity'],
                'weight' => $w,
            );
        }
        return $items;
    }

    /**
     * @param int $id
     * @param string $rate_id
     * @param waContact $contact
     * @return array
     * @throws waException
     */
    public function getRate($id = null, &$rate_id = null, $contact = null)
    {
        if (!$id) {
            $shipping = $this->getSessionData('shipping');
            if (!$shipping) {
                return array();
            }
            $id = $shipping['id'];
            $rate_id = $shipping['rate_id'];
        }
        if (!$contact) {
            $contact = $this->getContact();
        }
        $plugin_model = new shopPluginModel();
        $plugin_info = $plugin_model->getById($id);
        $plugin = shopShipping::getPlugin($plugin_info['plugin'], $id);
        $cart = new shopCart();
        $total = $cart->total();
        $currency = $plugin->allowedCurrency();
        $current_currency = wa('shop')->getConfig()->getCurrency(false);
        if ($currency != $current_currency) {
            $total = shop_currency($total, $current_currency, $currency, false);
        }
        $rates = $plugin->getRates($this->getItems($plugin->allowedWeightUnit()), $this->getAddress($contact), array('total_price' => $total));
        if (!$rates) {
            return false;
        }
        if (is_string($rates)) {
            return $rates;
        }
        if ($rate_id === null) {
            $rate_id = key($rates);
        }
        if (isset($rates[$rate_id])) {
            $result = $rates[$rate_id];
        } else {
            $result = array('rate' => 0);
        }
        if ($result['rate']) {
            if (is_array($result['rate'])) {
                $result['rate'] = max($result['rate']);
            }
            if ($currency != $current_currency) {
                $result['rate'] = shop_currency($result['rate'], $currency, $current_currency, false);
            }
            // rounding
            if ($result['rate'] && wa()->getSetting('round_shipping')) {
                $result['rate'] = shopRounding::roundCurrency($result['rate'], $current_currency);
            }
        }
        $result['plugin'] = $plugin->getId();
        $result['name'] = $plugin_info['name'].(!empty($result['name']) ? ' ('.$result['name'].')': '');
        return $result;
    }

    /**
     * @param waContact $contact
     * @return array
     */
    public function getAddress($contact = null)
    {
        if ($contact === null) {
            $contact = $this->getContact();
        }
        if (!$contact) {
            return array();
        }
        $address = $contact->getFirst('address.shipping');
        if ($address) {
            return $address['data'];
        } else {
            return array();
        }
    }

    /**
     * @return array
     * @todo: translate
     */
    public function getErrors()
    {
        $errors = array();
        $shipping = $this->getSessionData('shipping');
        if (!$shipping || empty($shipping['id'])) {
            $errors[] = _w('Shipping option is not defined. Please return to the shipping option checkout step to continue.');
        }
        return $errors;
    }

    public function execute()
    {
        if ($shipping_id = waRequest::post('shipping_id')) {

            if ($data = waRequest::post('customer_'.$shipping_id)) {

                $config =  wa('shop')->getConfig();
                /**
                 * @var shopConfig $config
                 */

                $settings = $config->getCheckoutSettings();
                if (!isset($settings['contactinfo']) ||
                    (!isset($settings['contactinfo']['fields']['address.shipping']) && !isset($settings['contactinfo']['fields']['address']))) {
                    $settings = $config->getCheckoutSettings(true);
                }
                $plugin = shopShipping::getPlugin(null, $shipping_id);
                $form = $this->getAddressForm($shipping_id, $plugin, $settings, array(), true);
                if (!$form->isValid()) {
                    return false;
                }

                $contact = $this->getContact();
                if (!$contact) {
                    $contact = new waContact();
                }
                if ($data && is_array($data)) {
                    foreach ($data as $field => $value) {
                        if (is_array($value) && ($old = $contact->get($field))) {
                            if (isset($old[0]['data'])) {
                                foreach ($old[0]['data'] as $k => $v) {
                                    if (!isset($value[$k])) {
                                        $value[$k] = $v;
                                    }
                                }
                            }
                        }
                        $contact->set($field, $value);
                    }
                    if (wa()->getUser()->isAuth()) {
                        $contact->save();
                    } else {
                        $this->setSessionData('contact', $contact);
                    }
                }
            }

            $rates = waRequest::post('rate_id');
            $rate_id = isset($rates[$shipping_id]) ? $rates[$shipping_id] : null;
            $rate = $this->getRate($shipping_id, $rate_id);
            if (is_string($rate)) {
                $rate = false;
            }
            $this->setSessionData('shipping', array(
                'id' => $shipping_id,
                'rate_id' => $rate_id,
                'name' => $rate ? $rate['name'] : '',
                'plugin' => $rate ? $rate['plugin'] : ''
            ));
            if (!$rate) {
                return false;
            }

            if ($comment = waRequest::post('comment')) {
                $this->setSessionData('comment', $comment);
            }
            if ($shipping_params = waRequest::post('shipping_'.$shipping_id)) {
                $params = $this->getSessionData('params', array());
                $params['shipping'] = $shipping_params;
                $this->setSessionData('params', $params);
            }

            if (!isset($rate['rate']) && isset($rate['comment'])) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    public function getOptions($config)
    {
        $view = wa()->getView();
        $view->assign(array(
            'config' => $config,
        ));
        return $view->fetch(wa()->getAppPath('templates/actions/settings/SettingsCheckoutShipping.html', 'shop'));
    }

    public function setOptions($config)
    {
        if (empty($config['service_agreement'])) {
            unset($config['service_agreement_hint']);
        } else if (isset($config['service_agreement_hint'])) {
            $config['service_agreement_hint'] = trim($config['service_agreement_hint']);
        }
        unset($config['service_agreement']);
        return $config;
    }

    protected function getCustomFields($id, waShipping $plugin)
    {
        $contact = $this->getContact();
        $order_params = $this->getSessionData('params', array());
        $shipping_params = isset($order_params['shipping']) ? $order_params['shipping'] : array();
        foreach ($shipping_params as $k => $v) {
            $order_params['shipping_params_'.$k] = $v;
        }
        $order = new waOrder(array('contact' => $contact,
            'contact_id' => $contact ? $contact->getId() : null,
            'params' => $order_params
        ));
        $custom_fields = $plugin->customFields($order);
        if (!$custom_fields) {
            return $custom_fields;
        }
        $params = array();
        $params['namespace'] = 'shipping_'.$id;
        $params['title_wrapper'] = '%s';
        $params['description_wrapper'] = '<span class="hint">%s</span>';
        $params['control_wrapper'] = '<div class="wa-name">%s</div><div class="wa-value"><p><span>%3$s %2$s</span></p></div>';
        $params['control_separator']='</span><span>';

        $selected_shipping = $this->getSessionData('shipping');
        $selected = $selected_shipping ? ($id == $selected_shipping['id']) : false;

        $controls = array();
        foreach ($custom_fields as $name => $row) {
            $row = array_merge($row, $params);
            if ($selected && isset($shipping_params[$name])) {
                $row['value'] = $shipping_params[$name];
            }
            $controls[$name] = waHtmlControl::getControl($row['control_type'], $name, $row);
        }

        return $controls;
    }
}
