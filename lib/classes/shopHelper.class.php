<?php

class shopHelper
{
    /**
     * Returns HTML code of a Webasyst icon.
     *
     * @param string|null $icon Icon type
     * @param string|null $default Default icon type to be used if $icon is empty.
     * @param int $size Icon size in pixels. Available sizes: 10, 16.
     * @param array $params Extra parameters:
     *     'class' => class name tp be added to icon's HTML code
     * @return string
     */
    public static function getIcon($icon, $default = null, $size = 16, $params = array())
    {
        if (!$icon && $default) {
            $icon = $default;
        }
        $class = isset($params['class']) ? ' '.htmlentities($params['class'], ENT_QUOTES, 'utf-8') : '';

        if ($icon) {
            if (preg_match('/^icon\.([\d\w_\-]+)$/', $icon, $matches)) {
                $size = ($size == 16) ? 16 : 10;
                $icon = "<i class='icon{$size} {$matches[1]}{$class}'></i>";
            } elseif (preg_match('@[\\/]+@', $icon)) {
                $size = max(10, min(16, $size));
                $icon = "<i class='icon{$size} {$class}' style='background: url({$icon})'></i>";
            } else {
                $size = ($size == 16) ? 16 : 10;
                $icon = "<i class='icon{$size} {$icon}{$class}'></i>";
            }
        }
        return $icon;
    }

    /**
     * Returns array of payment methods.
     *
     * @param array $order Array of order data whose parameters must be pre-filled in payment method's custom fields.
     * @return array
     */
    public static function getPaymentMethods($order = array())
    {
        $plugin_model = new shopPluginModel();
        $methods = $plugin_model->listPlugins(shopPluginModel::TYPE_PAYMENT);
        $order_params = $order ? $order['params'] : array();
        $order = new waOrder(
            array(
                'contact_id' => $order ? $order['contact_id'] : null,
                'contact'    => $order ? new waContact($order['contact_id']) : null,
                'params'     => $order_params
            )
        );
        foreach ($methods as $m_id => $m) {
            $plugin = shopPayment::getPlugin($m['plugin'], $m['id']);
            $custom_fields = $plugin->customFields($order);
            if ($custom_fields) {
                $params = array();
                $params['namespace'] = 'payment_'.$m['id'];
                $params['title_wrapper'] = '%s';
                $params['description_wrapper'] = '<br><span class="hint">%s</span>';
                $params['control_wrapper'] = '<div class="name">%s</div><div class="value">%s %s</div>';

                $controls = array();
                foreach ($custom_fields as $name => $row) {
                    $row = array_merge($row, $params);
                    if (!empty($order_params['payment_id']) && ($m['id'] == $order_params['payment_id']) && isset($order_params['payment_params_'.$name])) {
                        $row['value'] = $order_params['payment_params_'.$name];
                    }
                    if (!empty($row['control_type'])) {
                        $controls[$name] = waHtmlControl::getControl($row['control_type'], $name, $row);
                    }
                }
                if ($controls) {
                    $custom_html = '';
                    foreach ($controls as $c) {
                        $custom_html .= '<div class="field">'.$c.'</div>';
                    }
                    $methods[$m_id]['custom_html'] = $custom_html;
                }
            }
        }
        return $methods;
    }

    /**
     * Returns unavailable payment methods for specified shipping method or shipping methods for which specified payment method is unavailable.
     *
     * @param string $type Method type for which other type will be considered as complimentary; acceptable values: 'payment' or 'shipping'
     * @param int $id Id of method for which methods of other type must be returned
     * @return array Method ids
     */
    public static function getDisabledMethods($type, $id)
    {
        $map = wa()->getSetting('shipping_payment_disabled', null, 'shop');
        if (!$map) {
            return array();
        }
        $result = array();
        $map = json_decode($map, true);
        if (is_array($map)) {
            $complementary = ($type == shopPluginModel::TYPE_PAYMENT) ? shopPluginModel::TYPE_SHIPPING : shopPluginModel::TYPE_PAYMENT;
            if ($complementary == shopPluginModel::TYPE_PAYMENT) {
                $result = isset($map[$id]) ? $map[$id] : array();
            } else {
                foreach ($map as $plugin_id => $values) {
                    if (in_array($id, $values)) {
                        $result[] = $plugin_id;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Returns available shipping methods and rates for specified address.
     *
     * @param array $address Address data
     * @param array $items Order items
     * @param array $params Optional extra parameters:
     *     'payment'     => [int] payment method id for which available shipping methods must be returned
     *     'currency'    => [string] currency code to convert shipping rates to
     *     'total_price' => [float] arbitrary total order items cost to be taken into account for obtaining shipping rates
     * @return array
     */
    public static function getShippingMethods($address = null, $items = array(), $params = array())
    {
        $plugin_model = new shopPluginModel();
        $options = array();
        if (!empty($params[shopPluginModel::TYPE_PAYMENT])) {
            $options[shopPluginModel::TYPE_PAYMENT] = $params[shopPluginModel::TYPE_PAYMENT];
        }
        $methods = $plugin_model->listPlugins(shopPluginModel::TYPE_SHIPPING, $options);
        if ($address !== null) {
            $config = wa('shop')->getConfig();
            /** @var $config shopConfig */
            $result = array();
            $currency = isset($params['currency']) ? $params['currency'] : $config->getCurrency();
            $params['allow_external_for'] = (array) ifempty($params['allow_external_for'], array());
            $dimensions = shopDimension::getInstance();
            foreach ($methods as $m) {
                if ($m['available']) {
                    $plugin = shopShipping::getPlugin($m['plugin'], $m['id']);
                    $plugin_info = $plugin->info($m['plugin']);
                    $plugin_currency = (array)$plugin->allowedCurrency();

                    $total = null;
                    if ($plugin_currency != $currency) {
                        if (!$config->getCurrencies($plugin_currency)) {
                            $result[$m['id']] = array(
                                'plugin'   => $m['plugin'],
                                'logo'     => $m['logo'],
                                'icon'     => $plugin_info['icon'],
                                'img'      => $plugin_info['img'],
                                'name'     => $m['name'],
                                'error'    => sprintf(
                                    _w('Shipping rate was not calculated because required currency %s is not defined in your store settings.'),
                                    implode(', ', $plugin_currency)
                                ),
                                'rate'     => '',
                                'currency' => $currency,
                                'external' => !empty($plugin_info['external']),
                            );
                            continue;
                        }
                    }
                    if (isset($params['total_price'])) {
                        if (!in_array($currency, $plugin_currency)) {
                            $total = shop_currency($params['total_price'], $currency, reset($plugin_currency), false);
                        } else {
                            $total = $params['total_price'];
                        }
                    } else {
                        foreach ($items as $item) {
                            if (!empty($item['price'])) {
                                $total += $item['price'] * (isset($item['quantity']) ? $item['quantity'] : 1);
                            }
                            if ($total && !in_array($currency, $plugin_currency)) {
                                $total = shop_currency($total, $currency, reset($plugin_currency), false);
                            }
                        }
                    }
                    $weight_unit = $plugin->allowedWeightUnit();
                    foreach ($items as & $item) {
                        if (!empty($item['weight'])) {
                            if (empty($item['original_weight'])) {
                                $item['original_weight'] = $item['weight'];
                            }
                            $item['weight'] = $dimensions->convert($item['original_weight'], 'weight', $weight_unit);
                        }
                    }
                    unset($item);

                    if (!empty($params['no_external']) && !empty($plugin_info['external']) && !in_array($m['id'], $params['allow_external_for'])) {
                        $rates = '';
                    } else {
                        $rates = $plugin->getRates($items, $address ? $address : array(), $total ? array('total_price' => $total) : array());
                    }
                    if (is_array($rates)) {
                        foreach ($rates as $rate_id => $info) {
                            if (is_array($info)) {
                                $rate = is_array($info['rate']) ? max($info['rate']) : $info['rate'];
                                $rate = (float)shop_currency($rate, reset($plugin_currency), $currency, false);
                                $result[$m['id'].'.'.$rate_id] = array(
                                    'plugin'   => $m['plugin'],
                                    'logo'     => $m['logo'],
                                    'icon'     => $plugin_info['icon'],
                                    'img'      => $plugin_info['img'],
                                    'name'     => $m['name'].(!empty($info['name']) ? ' ('.$info['name'].')' : ''),
                                    'rate'     => $rate,
                                    'currency' => $currency,
                                    'external' => !empty($plugin_info['external']),
                                );
                                foreach(array('est_delivery','comment') as $field) {
                                    if(isset($info[$field]) && !is_null($info[$field])) {
                                        $result[$m['id'].'.'.$rate_id][$field] = $info[$field];
                                    }
                                }
                            }
                        }
                    } elseif (is_string($rates)) {
                        $result[$m['id']] = array(
                            'plugin'   => $m['plugin'],
                            'logo'     => $m['logo'],
                            'icon'     => $plugin_info['icon'],
                            'img'      => $plugin_info['img'],
                            'name'     => $m['name'],
                            'error'    => $rates,
                            'rate'     => '',
                            'currency' => $currency,
                            'external' => !empty($plugin_info['external']),
                        );
                    }
                }
            }
            return $result;
        } else {
            // !!! TODO: filter not-available methods?..
            return $methods;
        }
    }

    /**
     * Returns array of print forms available for specified order.
     *
     * @param array|null $order Order data; if not specified, print forms applicable to any orders are returned
     * @return array
     */
    public static function getPrintForms($order = null)
    {
        $plugins = wa('shop')->getConfig()->getPlugins();
        foreach ($plugins as $id => $plugin) {
            if (empty($plugin['printform'])) {
                unset($plugins[$id]);
            }
        }
        if ($order) {

            $type = 'payment';
            $key = ifempty($order['params'][$type.'_id']);
            try {
                if (!empty($key) && ($plugin = shopPayment::getPlugin(null, $key)) && method_exists($plugin, 'getPrintForms')) {
                    $forms = $plugin->getPrintForms(shopPayment::getOrderData($order));
                    foreach ($forms as $id => $form) {
                        if (isset($form['emailprintform'])) {
                            $form['mail_url'] = "?module=order&action=sendprintform&form_id={$type}.{$id}";
                        }
                        $plugins["{$type}.{$id}"] = $form;
                    }
                }
            } catch (waException $e) {
            }

            $type = 'shipping';
            $key = ifempty($order['params'][$type.'_id']);
            try {
                if (!empty($key) && ($plugin = shopShipping::getPlugin(null, $key)) && method_exists($plugin, 'getPrintForms')) {
                    $forms = $plugin->getPrintForms(shopPayment::getOrderData($order));
                    foreach ($forms as $id => $form) {
                        if (isset($form['emailprintform'])) {
                            $form['mail_url'] = "?module=order&action=sendprintform&form_id={$type}.{$id}";
                        }
                        $plugins["{$type}.{$id}"] = $form;
                    }
                }
            } catch (waException $e) {
            }

            foreach ($plugins as $plugin_id => & $plugin) {
                if (strpos($plugin_id, '.')) {
                    $plugin['url'] = "?module=order&action=printform&form_id={$plugin_id}&order_id={$order['id']}";
                } else {
                    if (!empty($plugin['emailprintform'])) {
                        $plugin['mail_url'] = "?module=order&action=sendprintform&plugin_id={$plugin_id}";
                    }

                    $plugin['url'] = "?plugin={$plugin_id}&module=printform&action=display&order_id={$order['id']}";
                }
            }
            unset($plugin);
        }
        //TODO separate backend & frontend
        return $plugins;
    }

    protected static $badges = array();

    /**
     * Returns HTML code for displaying one of default product image badges.
     *
     * @param string $code Badge code: 'new', 'bestseller', or 'lowprice'
     * @return string
     */
    public static function getBadgeHtml($code)
    {
        if (!self::$badges) {
            self::$badges = shopProductModel::badges();
        }
        if (isset(self::$badges[$code])) {
            return self::$badges[$code]['code'];
        }
        return $code;
    }

    /**
     * Returns HTML code of product image badge.
     *
     * @param array $image Image data array containing elements 'badge_type' and, optionally, 'badge_code' (for custom badges).
     * @return string
     */
    public static function getImageBadgeHtml($image)
    {
        if (!isset($image['badge_type'])) {
            return '';
        }
        if (shopProductImagesModel::isCustomBadgeType($image['badge_type'])) {
            return isset($image['badge_code']) ? $image['badge_code'] : '';
        }
        return shopProductImagesModel::getBadgeCode($image['badge_type']);
    }

    /**
     * Returns Gravatar URL for specified email address.
     * @see http://gravatar.com/site/implement/images/php/
     *
     * @param string $email Email address
     * @param int $size Size in pixels, defaults to 50
     * @param string $default Default image set to use. Available image sets: 'custom', '404', 'mm', 'identicon', 'monsterid', 'wavatar'.
     * @return string
     */
    public static function getGravatar($email, $size = 50, $default = 'mm')
    {
        if ($default == 'custom') {
            // Note that we cannot use @2x versions here since Gravatar
            // removes the @ symbol (even escaped) from the URL before redirect.
            $default = wa()->getRootUrl(true).'wa-content/img/userpic'.$size.'.jpg';
            $default = urlencode($default);
        }
        return '//www.gravatar.com/avatar/'.md5(strtolower(trim($email)))."?size=$size&default=$default";
    }

    /**
     * Adds various extra data to specified orders.
     *
     * @param array $orders Orders array
     * @param bool $single Whether only one order is specified; only in this case modified order data array is returned
     * @return null|array
     */
    public static function workupOrders(&$orders, $single = false)
    {
        if ($single) {
            $orders = array($orders);
        }


        $workflow = new shopWorkflow();
        $states = $workflow->getAllStates();
        foreach ($orders as & $order) {
            $order['id_str'] = self::encodeOrderId($order['id']);
            $order['total_str'] = wa_currency_html($order['total'], $order['currency']);
            if (!empty($order['create_datetime'])) {
                $order['create_datetime_str'] = wa_date('humandatetime', $order['create_datetime']);
            }
            $state = isset($states[$order['state_id']]) ? $states[$order['state_id']] : null;

            $icon = '';
            $style = '';
            if ($state) {
                /**
                 * @var shopWorkflowState $state
                 */
                $icon = $state->getOption('icon');
                $style = $state->getStyle();
            }
            $order['icon'] = $icon;
            $order['style'] = $style;

            if (isset($order['params'])) {
                // shipping_address_formatted
                $shipping_address = self::getOrderAddress($order['params'], 'shipping');
                $formatter = new waContactAddressOneLineFormatter();
                $order['shipping_address_formatted'] = $formatter->format(array('data' => $shipping_address));
                $order['shipping_address_formatted'] = $order['shipping_address_formatted']['value'];

                // Shipping and payment option names
                if (isset($order['params']['shipping_name'])) {
                    $order['shipping_name'] = htmlspecialchars($order['params']['shipping_name']);
                } else {
                    $order['shipping_name'] = '<span class="hint">'._w('not specified').'</span>';
                }
                if (isset($order['params']['payment_name'])) {
                    $order['payment_name'] = htmlspecialchars($order['params']['payment_name']);
                } else {
                    $order['payment_name'] = '<span class="hint">'._w('not specified').'</span>';
                }
            }
        }

        if ($single) {
            $orders = $orders[0];
            return $orders;
        }
    }

    /**
     * Returns customer address data for specified order.
     *
     * @param array $order_params Array of order address parameters with keys of the form 'shipping_address.***' or 'payment_address.***'
     * @param string $addr_type Address type: 'shipping' or 'payment'
     * @return array
     */
    public static function getOrderAddress($order_params, $addr_type)
    {
        $address = array();
        foreach (waContactFields::get('address')->getFields() as $k => $v) {
            $address[$k] = ifset($order_params[$addr_type.'_address.'.$k]);
        }
        return $address;
    }

    /**
     * Returns customer's shipping address written in one string.
     *
     * @param array $order_params 'params' element of order data array returned by getOrder() method of shopOrderModel class
     * @param bool $for_map Whether full or brief address information must be returned; defaults to true
     * @return string
     */
    public static function getShippingAddressText($order_params, $for_map = true)
    {
        $address = array();
        foreach (waContactFields::get('address')->getFields() as $k => $v) {
            $address[$k] = ifset($order_params['shipping_address.'.$k]);
        }

        if ($for_map) {
            $address_f = array();
            foreach (array('country', 'region', 'zip', 'city', 'street') as $k) {
                if (empty($address[$k])) {
                    continue;
                } elseif ($k == 'country') {
                    $address_f[$k] = waCountryModel::getInstance()->name(ifempty($address['country']));
                } elseif ($k == 'region') {
                    $address_f['region'] = '';
                    if (!empty($address['country']) && !empty($address['region'])) {
                        $model = new waRegionModel();
                        if ($region = $model->get($address['country'], $address['region'])) {
                            $address_f['region'] = $region['name'];
                        }
                    }
                } else {
                    $address_f[$k] = $address[$k];
                }
            }
            return implode(', ', $address_f);
        }
        return implode(', ', $address);
    }

    /**
     * Returns order id formatted in accordance with custom format specified in store settings.
     *
     * @param int $id
     * @return string
     */
    public static function encodeOrderId($id)
    {
        return str_replace('{$order.id}', $id, wa('shop')->getConfig()->getOrderFormat());
    }

    /**
     * Returns 'clean' order id without custom format applied which is specified in store settings.
     *
     * @param string $id
     * @return string Decoded order id, or empty string on failure
     */
    public static function decodeOrderId($id)
    {
        $format = wa('shop')->getConfig()->getOrderFormat();
        $format = '/^'.str_replace('\{\$order\.id\}', '(\d+)', preg_quote($format, '/')).'$/';
        if (preg_match($format, $id, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * Returns HTML code of stock icon (normal, low, critical).
     *
     * @param int|null $count SKU stock count; if not specified, normal icon is returned
     * @param int|null $stock_id Id of stock whose limit settings must be taken into account; if not specified, default values 5 and 2 are used
     * @param bool $include_text Whether text '*** items left' must be added to icon
     * @return string
     */
    public static function getStockCountIcon($count, $stock_id = null, $include_text = false)
    {
        static $stocks = array();
        if (!$stocks) {
            $model = new shopStockModel();
            $stocks = $model->getAll('id');
        }
        if ($count === null) {
            $icon = "<i class='icon10 status-green' title='"._w("In stock")."'></i>";
        } else {
            if (!$stock_id || empty($stocks[$stock_id])) {
                $bounds = array(
                    'critical_count' => shopStockModel::CRITICAL_DEFAULT,
                    'low_count'      => shopStockModel::LOW_DEFAULT
                );
            } else {
                $bounds = $stocks[$stock_id];
            }
            if ($count <= 0) {
                $icon = "<i class='icon10 status-red' title='"._w("Out of stock")."'></i>";
                $warn = 's-stock-warning-none';
            } else {
                if ($count <= $bounds['critical_count']) {
                    $icon = "<i class='icon10 status-red' title='"._w("Almost out of stock")."'></i>";
                    $warn = 's-stock-warning-none';
                } elseif ($count > $bounds['critical_count'] && $count <= $bounds['low_count']) {
                    $icon = "<i class='icon10 status-yellow' title='"._w("Low stock")."'></i>";
                    $warn = 's-stock-warning-low';
                } else {
                    $icon = "<i class='icon10 status-green' title='"._w("In stock")."'></i>";
                    $warn = '';
                }
            }
            if ($count !== null && $include_text) {
                $icon .= "<span class='small $warn'>$count left</span>";
            }
        }
        return $icon;
    }

    /**
     * Returns instance of class waContactForm.
     *
     * @param int|waContact|null $id Optional id of contact or contact object whose data must be pre-filled in contact form.
     * @param bool $ensure_address Whether address fields must be included regardless of store's contact fields settings.
     * @return waContactForm|shopContactForm
     */
    public static function getCustomerForm($id = null, $ensure_address = false, $checkout = false)
    {
        $contact = null;
        if ($id) {
            if (is_numeric($id)) {
                $contact = new waContact($id);
            } elseif ($id instanceof waContact) {
                $contact = $id;
            }
            $contact && $contact->getName(); // make sure contact exists; throws exception otherwise
        }

        $settings = wa('shop')->getConfig()->getCheckoutSettings();
        if (!isset($settings['contactinfo'])) {
            $settings = wa('shop')->getConfig()->getCheckoutSettings(true);
        }

        $fields_config = ifset($settings['contactinfo']['fields'], array());
        $address_config = ifset($fields_config['address'], array());
        unset($fields_config['address']);

        if ($ensure_address && !isset($fields_config['address.billing']) && !isset($fields_config['address.shipping'])) {
            // In customer center, we want to show address field even if completely disabled in settings.
            $fields_config['address'] = $address_config;
        } else {
            if (wa()->getEnv() == 'backend') {
                // Tweaks for backend order editor.
                // We want shipping address to show even if disabled in settings.
                // !!! Why is that?.. No idea. Legacy code.
                if (!isset($fields_config['address.shipping'])) {
                    $fields_config['address.shipping'] = $address_config;
                }
                // When an existing contact has address specified, we want to show all the data fields
                if ($contact) {
                    foreach (array('address.shipping', 'address.billing') as $addr_field_id) {
                        if (!empty($fields_config[$addr_field_id])) {
                            $address = $contact->getFirst($addr_field_id);
                            if ($address && !empty($address['data'])) {
                                foreach ($address['data'] as $subfield => $v) {
                                    if (!isset($fields_config[$addr_field_id]['fields'][$subfield])) {
                                        $fields_config[$addr_field_id]['fields'][$subfield] = array();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        foreach (array('address', 'address.shipping', 'address.billing') as $fid) {
            if (isset($fields_config[$fid]) && !empty($fields_config[$fid]['fields']['country'])) {
                if (!isset($fields_config[$fid]['fields']['country']['value'])) {
                    $fields_config[$fid]['fields']['country']['value'] = wa('shop')->getConfig()->getGeneralSettings('country');
                }
            }
        }

        if ($checkout && !wa()->getUser()->isAuth()) {
            $form = shopContactForm::loadConfig(
                $fields_config,
                array(
                    'namespace' => 'customer'
                )
            );

        } else {
            $form = waContactForm::loadConfig(
                $fields_config,
                array(
                    'namespace' => 'customer'
                )
            );
        }
        $contact && $form->setValue($contact);
        return $form;
    }

    /**
     * Suggests a URL part generated from specified string.
     *
     * @param string $str Specified string
     * @param boolean $strict Whether a default value must be generated if provided string results in an empty URL
     * @return string
     */
    public static function transliterate($str, $strict = true)
    {
        $str = preg_replace('/\s+/u', '-', $str);
        if ($str) {
            foreach (waLocale::getAll() as $lang) {
                $str = waLocale::transliterate($str, $lang);
            }
        }
        $str = preg_replace('/[^a-zA-Z0-9_-]+/', '', $str);
        if ($strict && !strlen($str)) {
            $str = date('Ymd');
        }
        return strtolower($str);
    }

    /**
     * Verifies current user's access rights to contact with specified id.
     *
     * @param int|null $contact_id Contact id. If not specified, access rights to all contacts are verified.
     * @return bool
     */
    public static function getContactRights($contact_id = null)
    {
        $rights = false;
        if (wa()->appExists('contacts')) {
            if ($contact_id) {
                wa('contacts');
                $contact_rights = new contactsRightsModel();
                if ($contact_rights->getRight(null, $contact_id)) {
                    $rights = true;
                }
            } else {
                $rights = wa()->getUser()->getRights('contacts');
                $rights = $rights && !empty($rights['backend']);
            }
        }
        return $rights;
    }

    /**
     * Returns HTML code of product rating control.
     *
     * @param int $rating Current rating value
     * @param int $size Rating icons size: 10 or 16; defaults to 10
     * @param bool $show_when_zero Whether HTML code must be returned for zero current rating
     * @return string
     */
    public static function getRatingHtml($rating, $size = 10, $show_when_zero = false)
    {
        $rating = round($rating * 2) / 2;
        if (!$rating && !$show_when_zero) {
            return '';
        }
        $html = '';
        for ($i = 1; $i <= 5; $i += 1) {
            $html .= '<i class="icon'.$size.' star';
            if ($i > $rating) {
                if ($i - $rating == 0.5) {
                    $html .= '-half';
                } else {
                    $html .= '-empty';
                }
            }
            $html .= '"></i>';
        }
        return $html;
    }

    /**
     * @param $url
     * @param waModel $context
     * @param int $length
     * @param string $field
     * @return string
     */
    public static function genUniqueUrl($url, $context, &$counter = 0, $length = 512, $field = 'url')
    {
        $counter = 0;
        $url = preg_replace('/\s+/', '-', $url);
        $url = shopHelper::transliterate($url);

        if (strlen($url) == 0) {
            $url = (time() << 24) + $counter++;
        } else {
            $url = mb_substr($url, 0, $length);
        }
        $url = mb_strtolower($url);

        $pattern = mb_substr($context->escape($url, 'like'), 0, $length - 3).'%';
        $sql = "SELECT `{$field}` FROM {$context->getTableName()} WHERE url LIKE '{$pattern}' ORDER BY LENGTH(`{$field}`)";

        $alike = $context->query($sql)->fetchAll('url');

        if (is_array($alike) && isset($alike[$url])) {
            $last = array_shift($alike);
            $counter = 1;
            do {
                $modifier = "-{$counter}";
                $_length = mb_strlen($modifier);
                $url = mb_substr($last['url'], 0, $length - $_length).$modifier;
            } while (isset($alike[$url]) && (++$counter < 100));
            if (isset($alike[$url])) {
                $short_uuid = (time() << 24) + $counter++;
                $_length = mb_strlen($short_uuid);

                $url = mb_substr($last['url'], 0, $length - $_length).$short_uuid;
            }
        }

        return mb_strtolower($url);
    }

    public static function getPromoImageUrl($id, $ext)
    {
        $v = @filemtime(wa('shop')->getDataPath('promos/'.$id.'.'.$ext, true));
        return wa('shop')->getDataUrl('promos/'.$id.'.'.$ext, true).($v ? '?v='.$v : '');
    }

    public static function getWritableTypes($contact = null)
    {
        if (!$contact) {
            $contact = wa()->getUser();
        } else {
            if (!$contact instanceof waContact) {
                $contact = new waContact($contact);
            }
        }

        $types_allowed = array();
        $type_model = new shopTypeModel();
        foreach ($type_model->getTypes() as $type_id => $type) {
            if ($contact->getRights('shop', 'type.'.$type_id) >= 2) {
                $types_allowed[$type_id] = $type;
            }
        }

        return $types_allowed;
    }



    /**
     * @param string $source storefront
     * @return string
     */
    public static function getStoreEmail($source)
    {
        $store_email = null;

        $notification_model = new shopNotificationModel();
        $sql = <<<SQL
SELECT DISTINCT n.source, n.transport, np.value
FROM shop_notification n
JOIN shop_notification_params np ON n.id = np.notification_id
WHERE
  np.name = 'from'
  AND
  n.transport = 'email'

SQL;
        if ($source) {
            $source = trim($source, '/*').'/*';
            $sql .= " AND n.source=s:source";
        }
        $sql .= ' LIMIT 1';

        if ($row = $notification_model->query($sql, compact('source'))->fetchRow()) {
            $store_email = $row['value'];
        }
        if (empty($store_email)) {
            $store_email = wa('shop')->getConfig()->getGeneralSettings('email');
        }
        return $store_email;
    }
}
