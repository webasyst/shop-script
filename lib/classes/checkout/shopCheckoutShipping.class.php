<?php

class shopCheckoutShipping extends shopCheckout
{
    protected $step_id = self::STEP_SHIPPING;

    public function display()
    {
        $options = $this->getShippingOptions();

        $selected_shipping = $this->getSelectedMethod();

        $methods = $this->getPlugins();
        foreach ($methods as $method_id => &$m) {
            $m = $this->workupShippingMethod($m, $options, $selected_shipping);
            if (!$m) {
                unset($methods[$method_id]);
            }
            unset($m);
        }

        $this->assign('checkout_shipping_methods', $methods);

        $shipping = $this->setSelectedMethod($selected_shipping, $methods);

        $this->assign('shipping', $shipping);

        $this->addFlowStep();
    }

    protected function getSelectedMethod()
    {
        if (waRequest::method() == 'post') {
            $shipping_id = waRequest::post('shipping_id');
            $rate_id = waRequest::post('rate_id');
            $selected_shipping = array(
                'id'      => $shipping_id,
                'rate_id' => isset($rate_id[$shipping_id]) ? $rate_id[$shipping_id] : '',
            );
        } else {
            $selected_shipping = $this->getSessionData('shipping', array());
        }
        return $selected_shipping;
    }

    protected function setSelectedMethod($selected_shipping, $methods)
    {
        $shipping = null;
        if ($selected_shipping) {
            if (isset($methods[$selected_shipping['id']])) {
                $shipping = $selected_shipping;
            }
        }

        if (!$shipping) {
            $default_method = '';
            foreach ($methods as $m) {
                if (empty($m['error'])) {
                    $default_method = $m['id'];
                    break;
                }
            }
            $shipping = array(
                'id'      => $default_method,
                'rate_id' => '',
            );
        }

        return $shipping;
    }

    protected function getShippingOptions()
    {
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */

        $settings = self::getCheckoutSettings();
        $options = array(
            'address_form'     => !isset($settings['contactinfo']) || !isset($settings['contactinfo']['fields']['address.shipping']),
            'address'          => $this->getAddress(),
            'currencies'       => $config->getCurrencies(),
            'current_currency' => $config->getCurrency(false),
            'settings'         => $settings,
        );

        $settings = $this->getExtendedCheckoutSettings();

        $empty = true;
        foreach ($options['address'] as $v) {
            if ($v) {
                $empty = false;
                break;
            }
        }

        if ($empty) {
            $options['address'] = array();
        }

        if (!$options['address']) {
            $options['shipping_address'] = array();
            $options['address_form'] = true;
            if (!empty($settings['contactinfo']['fields']['address'])) {
                foreach ($settings['contactinfo']['fields']['address']['fields'] as $k => $f) {
                    if (!empty($f['value'])) {
                        $options['shipping_address'][$k] = $f['value'];
                    }
                }
            }
        } else {
            $options['shipping_address'] = $options['address'];
        }

        return $options;
    }

    protected function getPlugins($id = null)
    {
        $options = array();

        # filter enabled at frontend plugins
        $shipping_id = waRequest::param('shipping_id');
        if ($shipping_id && is_array($shipping_id)) {
            $options['id'] = $shipping_id;
        }

        if ($id) {
            if (empty($options['id'])) {
                $options['id'] = $id;
            } elseif (in_array($id, $options['id'])) {
                $options['id'] = $id;
            } else {
                return array();
            }
        }

        # filter applicable shipping plugins
        if ($payment_id = $this->getSessionData('payment')) {
            if (self::getStepNumber($this->step_id) > self::getStepNumber(self::STEP_PAYMENT)) {
                $options[shopPluginModel::TYPE_PAYMENT] = $payment_id;
            }
        }

        return $this->plugin_model->listPlugins(shopPluginModel::TYPE_SHIPPING, $options);
    }

    protected function workupShippingMethod($m, $method_options, $selected_shipping = array())
    {
        try {
            $plugin = shopShipping::getPlugin($m['plugin'], $m['id']);

            $plugin_info = $plugin->info($m['plugin']);

            $m['icon'] = $plugin_info['icon'];
            $m['img'] = $plugin_info['img'];
            $m['currency'] = $plugin->allowedCurrency();
            $m['external'] = ($selected_shipping && $selected_shipping['id'] == $m['id']) ? 0 : $plugin->getProperties('external');

            if (empty($m['available'])) {
                $m['rates'] = _w('Not available');

            } elseif ($plugin->isAllowedAddress($method_options['shipping_address'])) {
                if ($m['external']) {
                    $m['rates'] = array();
                } else {

                    $options = array(
                        'weight'     => $plugin->allowedWeightUnit(),
                        'dimensions' => $plugin->allowedLinearUnit(),
                        'currency'   => $m['currency'],
                    );

                    $items = $this->getItems();

                    $shipping_items = shopHelper::workupOrderItems($items, array_filter($options));

                    $params = $this->getShippingParams($plugin, $m);

                    $m['rates'] = $plugin->getRates($shipping_items, $method_options['shipping_address'], $params);
                }
            } else {
                $m['rates'] = false;
            }

            if (is_array($m['rates'])) {
                if (!isset($method_options['currencies'][$m['currency']])) {
                    $m['rate'] = 0;
                    $format = _w('Shipping rate was not calculated because required currency %s is not defined in your store settings.');
                    $m['error'] = sprintf($format, $m['currency']);
                    return $m;
                }

                foreach ($m['rates'] as &$r) {
                    if (is_array($r['rate'])) {
                        $r['rate'] = max($r['rate']);
                    }

                    // Apply rounding. This converts all rates to current frontend currency.
                    if ($r['rate'] && wa()->getSetting('round_shipping')) {
                        $r['rate'] = shopRounding::roundCurrency(
                            shop_currency($r['rate'], $m['currency'], $method_options['current_currency'], false),
                            $method_options['current_currency']
                        );
                        $r['currency'] = $method_options['current_currency'];
                    }
                }
                unset($r);

                if (wa()->getSetting('round_shipping')) {
                    $m['currency'] = $method_options['current_currency'];
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
                if ($method_options['address']) {
                    $m['error'] = $m['rates'];
                } else {
                    $m['rates'] = array();
                    $m['rate'] = null;
                }
            } else {
                return null;
            }

            // When free shipping coupon is used, display all rates as 0
            if (isset($m['rate']) && ($m['rate'] !== null)) {
                if ($this->isFreeShipping()) {
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

            $f = $this->getAddressForm($m['id'], $plugin, null, $method_options['address'], $method_options['address_form']);
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

            return $m;
        } catch (waException $ex) {
            waLog::log($ex->getMessage(), 'shop/checkout.error.log');
            return null;
        }
    }

    public function getAddressForm($method_id, waShipping $plugin, $config, $contact_address, $address_form)
    {
        if ($config === null) {
            $config = $this->getExtendedCheckoutSettings();
        }
        $config_address = isset($config['contactinfo']['fields']['address.shipping']) ?
            $config['contactinfo']['fields']['address.shipping'] :
            (isset($config['contactinfo']['fields']['address']) ? $config['contactinfo']['fields']['address'] : array());

        $address_fields = $plugin->requestedAddressFields();
        $disabled_only = $address_fields === array() ? false : true;
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
                    $fields = $config_address['fields'];
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

        if ($address_form || ifset($config, 'shipping', 'prompt_type', null) == 2) {
            if (ifset($config, 'shipping', 'prompt_type', null) == 1) {
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
                $sort = array_keys($config_address['fields']);
                $address['fields'] = $this->ksortAsSuggested($address['fields'], $sort);
            }

            return waContactForm::loadConfig(array('address.shipping' => $address), array('namespace' => 'customer_'.$method_id));
        } else {
            return null;
        }
    }

    /**
     * uksort and etc is not stable, so it is bummer
     *
     * Read documentation here
     * https://www.php.net/manual/ru/function.uksort.php
     *
     * This method has stability property
     * Key that are not found in order array will be set in the end of result array with saving original sorting (stability)
     * 
     * @param $array
     * @param array $order
     * @return array
     */
    function ksortAsSuggested($array, $order = array())
    {
        $result = array();
        foreach ($order as $key) {
            if (isset($array[$key])) {
                $result[$key] = $array[$key];
                unset($array[$key]);
            }
        }
        foreach ($array as $key => $value) {
            $result[$key] = $value;
        }
        return $result;
    }

    private $items = null;

    public function getItems()
    {
        if ($this->items === null) {
            $this->items = array();
            $cart_items = $this->cart->items();

            $units = array(
                'weight'     => true,
                'dimensions' => true,
            );

            #get actual order items weight
            shopShipping::extendItems($cart_items, $units);

            foreach ($cart_items as $item) {
                $this->items[] = array(
                    'name'               => $item['name'],
                    'price'              => $item['price'],
                    'currency'           => $item['currency'],
                    'quantity'           => $item['quantity'],
                    'type'               => ifset($item, 'type', null),
                    'product_id'         => ifset($item, 'product_id', null),
                    'sku_id'             => ifset($item, 'sku_id', null),
                    'service_id'         => ifset($item, 'service_id', null),
                    'service_variant_id' => ifset($item, 'service_variant_id', null),
                    'weight'             => ifset($item, 'weight', null),
                    'height'             => ifset($item, 'height', null),
                    'width'              => ifset($item, 'width', null),
                    'length'             => ifset($item, 'length', null),
                );
            }
        }
        return $this->items;
    }

    private $items_total = null;

    public function getItemsTotal()
    {
        if ($this->items_total === null) {
            $this->items_total = shopShipping::getItemsTotal($this->getItems());
            $this->items_total['total_price'] = $this->cart->total();
        }
        return $this->items_total;
    }

    protected function extendShippingParams($params, $id)
    {
        $shipping_params = shopShipping::getParams($id);
        if (is_array($shipping_params)) {
            $params['shipping_params'] = $shipping_params;
        } else {
            $shipping = $this->getSessionData('shipping', array());
            if (ifset($shipping['id']) == $id) {
                $session_params = $this->getSessionData('params', array());
                $params['shipping_params'] = ifset($session_params['shipping']);
            }
        }

        return $params;
    }

    /**
     * @param int $id
     * @param string $rate_id
     * @param waContact $contact
     * @return array|false|string
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

        $plugin_info = $this->plugin_model->getById($id);

        try {
            $plugin = shopShipping::getPlugin($plugin_info['plugin'], $id);
        } catch (waException $ex) {
            switch ($ex->getCode()) {
                case 404:
                    $this->assign('error', _w('Shipping option is not defined. Please return to the shipping option checkout step to continue.'));
                    break;
                default:
                    $this->assign('error', $ex->getMessage());
                    break;
            }
            return false;
        }

        $items = $this->getItems();

        $params = $this->getShippingParams($plugin, $plugin_info);

        shopShipping::convertItemsDimensions($items, $plugin);

        $rates = $plugin->getRates($items, $this->getAddress($contact), $params);
        if (!$rates) {
            return false;
        }
        if (is_string($rates)) {
            $this->assign('error', $rates);
            return $rates;
        }
        if ($rate_id === null) {
            $rate_id = key($rates);
        }
        if (isset($rates[$rate_id])) {
            $result = $rates[$rate_id];
        } elseif (!in_array($rate_id, array(null, false), true)) {
            return _w('Shipping option is not defined. Please return to the shipping option checkout step to continue.');
        } else {
            $result = array('rate' => 0);
        }
        if ($result['rate']) {
            if (is_array($result['rate'])) {
                $result['rate'] = max($result['rate']);
            }

            $currency = $plugin->allowedCurrency();

            /** @var shopConfig $shop_config */
            $shop_config = wa('shop')->getConfig();

            /** @var string $current_currency */
            $current_currency = $shop_config->getCurrency(false);

            // if $current_currency == $currency it's will be rounded to currency precision
            if ($result['rate']) {
                $result['rate'] = shop_currency($result['rate'], $currency, $current_currency, false);
            }

            // rounding
            if ($result['rate'] && wa()->getSetting('round_shipping')) {
                $result['rate'] = shopRounding::roundCurrency($result['rate'], $current_currency);
            }
        }
        $result['plugin'] = $plugin->getId();
        $result['name'] = $plugin_info['name'].(!empty($result['name']) ? ' ('.$result['name'].')' : '');
        $result['tax_id'] = ifset($plugin_info['options']['tax_id']);
        return $result;
    }

    private $shipping_params = null;

    protected function getShippingParams($plugin, $plugin_info)
    {
        if ($this->shipping_params === null) {
            $items = $this->getItems();

            $this->shipping_params = shopShipping::getItemsTotal($items);
            $this->shipping_params['total_price'] = $this->cart->total();
        }

        $params = shopShipping::workupShippingParams($this->shipping_params, $plugin, $plugin_info);
        return $this->extendShippingParams($params, $plugin_info['id']);
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
     */
    public function getErrors()
    {
        $errors = array();
        $shipping = $this->getSessionData('shipping');
        if (!$shipping || empty($shipping['id'])) {
            $errors[] = _w('Shipping option is not defined. Please return to the shipping option checkout step to continue.');
        } elseif (!isset($shipping['rate_id'])) {
            //XXX TODO check rate id
            $errors[] = _w('Shipping option is not defined. Please return to the shipping option checkout step to continue.');
        } else {
            $rate = $this->getRate();
            if (is_string($rate)) {
                $errors[] = $rate;
            } elseif (is_array($rate) && !isset($rate['rate'])) {
                $errors[] = ifset($rate, 'comment', _w('Shipping option is not defined. Please return to the shipping option checkout step to continue.'));
            }
        }

        return $errors;
    }

    private function getExtendedCheckoutSettings()
    {
        static $settings = null;
        if ($settings === null) {
            $settings = self::getCheckoutSettings();
            if (!isset($settings['contactinfo'])
                ||
                (
                    !isset($settings['contactinfo']['fields']['address.shipping'])
                    && !isset($settings['contactinfo']['fields']['address'])
                )
            ) {
                $config = wa('shop')->getConfig();
                /**
                 * @var shopConfig $config
                 */
                $settings = $config->getCheckoutSettings(true);
            }
        }
        return $settings;
    }

    public function execute()
    {
        if ($shipping_id = waRequest::post('shipping_id', null, waRequest::TYPE_INT)) {

            if ($data = waRequest::post('customer_'.$shipping_id)) {

                try {
                    $plugins = $this->getPlugins($shipping_id);
                    if (!$plugins) {
                        return false;
                    }
                    $plugin_info = reset($plugins);
                    if (empty($plugin_info['available'])) {
                        return false;
                    }
                    $plugin = shopShipping::getPlugin($plugin_info['plugin'], $plugin_info['id']);
                } catch (waException $ex) {
                    switch ($ex->getCode()) {
                        case 404:
                            $this->assign('error', _w('Shipping option is not defined. Please return to the shipping option checkout step to continue.'));
                            break;
                        default:
                            $this->assign('error', $ex->getMessage());
                            break;
                    }
                    return false;
                }
                $form = $this->getAddressForm($shipping_id, $plugin, null, array(), true);
                if (!($form instanceof waContactForm) || !$form->isValid()) {
                    return false;
                }

                $contact = $this->getContact();

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
                $this->assign('error', $rate);
                $rate = false;
            } elseif ($rate['rate'] !== null) {
                if ($this->isFreeShipping()) {
                    $rate['rate'] = 0;
                }
            }

            $shipping = array(
                'id'      => $shipping_id,
                'rate_id' => $rate_id,
                'rate'    => $rate ? $rate['rate'] : 0,
                'name'    => $rate ? $rate['name'] : '',
                'plugin'  => $rate ? $rate['plugin'] : '',
            );
            $this->setSessionData('shipping', $shipping);
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
        } elseif (isset($config['service_agreement_hint'])) {
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
        $order = new waOrder(array(
            'contact'    => $contact,
            'contact_id' => $contact ? $contact->getId() : null,
            'params'     => $order_params,
        ));
        $custom_fields = $plugin->customFields($order);
        if (!$custom_fields) {
            return $custom_fields;
        }

        $selected_shipping = $this->getSessionData('shipping');
        $selected = $selected_shipping ? ($id == $selected_shipping['id']) : false;

        if ($selected) {
            foreach ($custom_fields as $name => &$row) {
                if (isset($shipping_params[$name])) {
                    $row['value'] = $shipping_params[$name];
                }
            }
        }

        return $this->getControls($custom_fields, 'shipping_'.$id);
    }

    protected function isFreeShipping()
    {
        $is_free = false;

        $coupon_code = $this->getSessionData('coupon_code');
        if (!empty($coupon_code)) {
            empty($cm) && ($cm = new shopCouponModel());
            $coupon = $cm->getByField('code', $coupon_code);
            if ($coupon && $coupon['type'] == '$FS') {
                $is_free = true;
            }
        }
        return $is_free;
    }
}
