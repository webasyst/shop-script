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

        $order_customer = null;
        if ($order && $order['contact_id']) {
            $order_customer = new waContact($order['contact_id']);
            try {
                $order_customer->getName();
            } catch (waException $e) {
                $order_customer = null;
            }
        }

        $order = new waOrder(
            array(
                'contact_id' => $order_customer ? $order['contact_id'] : null,
                'contact'    => $order_customer,
                'params'     => $order_params,
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
                $params['control_separator'] = '</div><div class="value>"';

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
     *     'no_external' => [bool]
     *     'allow_external_for' => int[] array of external shipping methods' ids
     * @return array
     */
    public static function getShippingMethods($address = null, $items = array(), $params = array())
    {
        if (isset($params['__methods'])) {
            // Unit tests use this to inject stub plugins into this method
            $methods = $params['__methods'];
            unset($params['__methods']);
        } else {
            $plugin_model = new shopPluginModel();
            $options = array();
            if (!empty($params[shopPluginModel::TYPE_SHIPPING])) {
                $options = (array)$params[shopPluginModel::TYPE_SHIPPING];
            }
            if (!empty($params[shopPluginModel::TYPE_PAYMENT])) {
                $options[shopPluginModel::TYPE_PAYMENT] = $params[shopPluginModel::TYPE_PAYMENT];
            }
            if (isset($params['customer_type'])) {
                $options['customer_type'] = $params['customer_type'];
            }
            $methods = $plugin_model->listPlugins(shopPluginModel::TYPE_SHIPPING, $options);
        }

        if ($address === null) {
            // !!! TODO: filter not-available methods?..
            return $methods;
        }

        /** @var $config shopConfig */
        $config = wa('shop')->getConfig();
        $params['currency'] = isset($params['currency']) ? $params['currency'] : $config->getCurrency();
        $params['allow_external_for'] = (array)ifempty($params['allow_external_for'], array());

        waNet::multiQuery(
            'shop.shipping',
            [
                'timeout' => ifset($params, 'timeout', 10),
            ]
        );

        shopShipping::extendItems($items);

        $result = array();
        foreach ($methods as &$m) {
            if (!$m['available']) {
                continue;
            }

            $method_id = $m['id'];
            if (isset($m['__instance'])) {
                // Unit tests use this to inject stub plugins into this method
                $plugin = $m['__instance'];
            } else {
                $plugin = shopShipping::getPlugin($m['plugin'], $method_id);
            }
            if (isset($m['__plugin_info'])) {
                $plugin_info = $m['__plugin_info'];
            } else {
                $plugin_info = $plugin->info($m['plugin']);
            }

            if (!empty($params['filter'])) {
                $matched = true;
                foreach ($params['filter'] as $property => $value) {
                    if ($value === null) {
                        if (isset($plugin_info[$property])) {
                            $matched = false;
                            break;
                        }
                    } elseif (!isset($plugin_info[$property])) {
                        $matched = false;
                        break;
                    } else {
                        if (is_array($value)) {
                            if (!in_array($plugin_info[$property], $value)) {
                                $matched = false;
                                break;
                            }
                        } elseif ($value != $plugin_info[$property]) {
                            $matched = false;
                            break;
                        }
                    }
                }
                if (!$matched) {
                    continue;
                }
            }

            $plugin_currency = (array)$plugin->allowedCurrency();

            #check plugin currency;
            if ($plugin_currency != $params['currency']) {
                if (!$config->getCurrencies($plugin_currency)) {
                    $result[$method_id] = array(
                        'plugin'               => $m['plugin'],
                        'plugin_original_name' => $plugin_info['name'],
                        'plugin_name'          => $m['name'],
                        'logo'                 => $m['logo'],
                        'icon'                 => $plugin_info['icon'],
                        'img'                  => $plugin_info['img'],
                        'name'                 => $m['name'],
                        'error'                => sprintf(
                            _w('Shipping rate was not calculated because required currency %s is not defined in your store settings.'),
                            implode(', ', $plugin_currency)
                        ),
                        'rate'                 => false,
                        'currency'             => $params['currency'],
                        'external'             => !empty($plugin_info['external']),
                    );
                    continue;
                }
            }

            # check allowed address
            if ($address && !$plugin->isAllowedAddress($address)) {
                continue;
            }

            $m['__instance'] = $plugin;
            $m['__plugin_info'] = $plugin_info;

            if (!empty($params['no_external'])
                && !empty($plugin_info['external'])
                && !in_array($method_id, $params['allow_external_for'])
            ) {
                $m['__rates'] = '';

            } else {
                $shipping_params = array();

                $total = self::getShippingItemsTotal($items, $plugin_currency, $params);

                # convert dimensions
                self::convertShippingItemsDimensions($items, $plugin);

                if ($total) {
                    $shipping_params['total_price'] = $total;
                }
                if (!empty($params['shipping_params'][$method_id])) {
                    $shipping_params['shipping_params'] = $params['shipping_params'][$method_id];
                }

                if (!empty($params['departure_datetime'])) {
                    $shipping_params['departure_datetime'] = $params['departure_datetime'];
                }

                $m['__rates'] = $plugin->getRates($items, $address ? $address : array(), $shipping_params);
            }

        }
        unset($m);

        waNet::multiQuery('shop.shipping');

        $data_fields = array(
            'type',
            'est_delivery',
            'comment',
            'custom_data',
            'delivery_date',
        );

        foreach ($methods as $m) {
            if (isset($m['__rates'])) {
                $rates = $m['__rates'];
                $method_id = $m['id'];
                $plugin_info = $m['__plugin_info'];

                /** @var waShipping $plugin */
                $plugin = $m['__instance'];

                if ($rates && ($rates instanceof waShipping)) {
                    $rates = $rates->getPromise();
                }
                if (is_array($rates)) {
                    $key = null;
                    foreach ($rates as $rate_id => $info) {
                        if (is_array($info)) {
                            $key = $method_id.'.'.$rate_id;
                            if (empty($params['raw_rate'])) {
                                $rate = is_array($info['rate']) ? max($info['rate']) : $info['rate'];
                                if ($rate !== null) {
                                    $rate = (float)shop_currency($rate, reset($plugin_currency), $params['currency'], false);
                                }
                            } else {
                                $rate = $info['rate'];
                            }
                            if (!empty($params['filter']['services_by_type'])) {
                                $rate_name = empty($info['name']) ? $m['name'] : $info['name'];
                                if (isset($info['service'])
                                    && ($length = strlen($info['service']))
                                    && (strpos($rate_name, $info['service']) === 0)
                                ) {
                                    $rate_name = preg_replace('@^[\s:\.,]+@', '', substr($rate_name, $length));
                                }
                            } else {
                                $rate_name = $m['name'].(!empty($info['name']) ? ' ('.$info['name'].')' : '');
                            }

                            $result[$key] = array(
                                'plugin'               => $m['plugin'],
                                'plugin_original_name' => $plugin_info['name'],
                                'plugin_name'          => $m['name'],
                                'logo'                 => $m['logo'],
                                'icon'                 => $plugin_info['icon'],
                                'img'                  => $plugin_info['img'],
                                'name'                 => $rate_name,
                                'service'              => ifset($info, 'service', (count($rates) > 1) || ($rate_name != $m['name']) ? $m['name'] : ''),
                                'rate'                 => $rate,
                                'currency'             => $params['currency'],
                                'external'             => !empty($plugin_info['external']),
                                'type'                 => ifset($plugin_info, 'type', null),
                            );

                            if ($rate === null) {
                                $result[$key]['error'] = ifset($info, 'comment', _w('Shipping option not specified'));
                                unset($info['comment']);
                            }

                            foreach ($data_fields as $field) {
                                if (isset($info[$field]) && !is_null($info[$field])) {
                                    $result[$key][$field] = $info[$field];
                                }
                            }
                        }
                    }

                    if ($key && !empty($params['custom_html']) && $plugin->getProperties('backend_custom_fields')) {
                        $order = new waOrder(
                            array(
                                'contact_id'       => null,
                                'contact'          => null,
                                'items'            => $items,
                                'shipping_address' => $address,
                                'shipping_params'  => ifset($params['shipping_params'][$method_id]),
                            )
                        );
                        $custom_fields = $plugin->customFields($order);
                        if ($custom_fields) {
                            $control_params = array();
                            $control_params['namespace'] = 'shipping_'.$m['id'];
                            $control_params['title_wrapper'] = '%s';
                            $control_params['description_wrapper'] = '<br><span class="hint">%s</span>';
                            $control_params['control_wrapper'] = '<div class="field"><div class="name">%s</div><div class="value">%s %s</div></div>';
                            $control_params['control_separator'] = '</div><div class="value">';

                            $controls = array();
                            foreach ($custom_fields as $name => $row) {
                                $row = array_merge($row, $control_params);
                                if (!empty($order_params['shipping_id']) && ($m['id'] == $order_params['shipping_id']) && isset($order_params['shipping_params_'.$name])) {
                                    $row['value'] = $order_params['shipping_params_'.$name];
                                }
                                if (!empty($row['control_type'])) {
                                    $controls[$name] = waHtmlControl::getControl($row['control_type'], $name, $row);
                                }
                            }
                            if ($controls) {
                                $custom_html = '';
                                foreach ($controls as $c) {
                                    $custom_html .= $c;
                                }
                                $result[$key]['custom_html'] = $custom_html;
                            }
                        }
                    }

                } elseif (is_string($rates)) {
                    $result[$m['id']] = array(
                        'plugin'               => $m['plugin'],
                        'plugin_original_name' => $plugin_info['name'],
                        'plugin_name'          => $m['name'],
                        'logo'                 => $m['logo'],
                        'icon'                 => $plugin_info['icon'],
                        'img'                  => $plugin_info['img'],
                        'name'                 => $m['name'],
                        'error'                => $rates,
                        'rate'                 => false,
                        'currency'             => $params['currency'],
                        'external'             => !empty($plugin_info['external']),
                    );
                } elseif (($rates === false) && in_array($m['id'], $params['allow_external_for'])) {
                    $result[$method_id] = array(
                        'plugin'               => $m['plugin'],
                        'plugin_original_name' => $plugin_info['name'],
                        'plugin_name'          => $m['name'],
                        'logo'                 => $m['logo'],
                        'icon'                 => $plugin_info['icon'],
                        'img'                  => $plugin_info['img'],
                        'name'                 => $m['name'],
                        'error'                => _w('Not available'),
                        'rate'                 => false,
                        'external'             => !empty($plugin_info['external']),
                    );
                }
            }
        }

        return $result;
    }

    private static function getShippingItemsTotal($items, $plugin_currency, $params)
    {
        $total = null;
        if (isset($params['total_price'])) {
            if (!in_array($params['currency'], $plugin_currency)) {
                $total = shop_currency($params['total_price'], $params['currency'], reset($plugin_currency), false);
            } else {
                $total = $params['total_price'];
            }
        } else {
            foreach ($items as $item) {
                if (!empty($item['price'])) {
                    $total += $item['price'] * (isset($item['quantity']) ? $item['quantity'] : 1);
                }
                if ($total && !in_array($params['currency'], $plugin_currency)) {
                    $total = shop_currency($total, $params['currency'], reset($plugin_currency), false);
                }
            }
        }

        return $total;
    }

    /**
     * @param $items
     * @param waShipping $plugin
     */
    private static function convertShippingItemsDimensions(&$items, $plugin)
    {
        $dimension_fields = array();

        $dimensions = shopDimension::getInstance();

        if ($weight_unit = $plugin->allowedWeightUnit()) {
            $dimension_fields['weight'] = array(
                'type' => 'weight',
                'unit' => $weight_unit,
            );
            unset($weight_unit);
        }

        if ($linear_unit = $plugin->allowedLinearUnit()) {
            $dimension_fields['height'] = array(
                'type' => 'length',
                'unit' => $linear_unit,
            );
            $dimension_fields['length'] = array(
                'type' => 'length',
                'unit' => $linear_unit,
            );
            $dimension_fields['width'] = array(
                'type' => 'length',
                'unit' => $linear_unit,
            );
            unset($linear_unit);
        }

        foreach ($items as &$item) {
            foreach ($dimension_fields as $field => $unit) {
                if (!empty($item[$field])) {
                    $original_field = 'original_'.$field;
                    if (empty($item[$original_field])) {
                        $item[$original_field] = $item[$field];
                    }
                    $item[$field] = $dimensions->convert($item[$original_field], $unit['type'], $unit['unit']);
                }
            }
        }
        unset($item);
    }

    /**
     * @deprecated
     * Use shopPrintforms::getOrderPrintforms instead of that method
     *
     * Returns array of ORDER print forms available for specified order.
     *
     * @param waOrder|array|null $order Order data; if not specified, print forms applicable to any orders are returned
     * @return array
     */
    public static function getPrintForms($order = null)
    {
        return shopPrintforms::getOrderPrintforms($order);
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
     * @param bool $full_protocol by default returns protocol-agnostic URL starting with // ; pass true to prepend with http://
     * @return string
     */
    public static function getGravatar($email, $size = 50, $default = 'mm', $full_protocol = false)
    {
        if ($default == 'custom') {
            // Note that we cannot use @2x versions here since Gravatar
            // removes the @ symbol (even escaped) from the URL before redirect.
            $url = wa()->getRootUrl(true).'wa-content/img/userpic'.$size.'.jpg';
            if (!file_exists($default)) {
                $url = wa()->getRootUrl(true).'wa-content/img/userpic50.jpg';
            }
            $default = urlencode($default);
        }

        if ($default !== 'custom') {
            $url = '//www.gravatar.com/avatar/'.md5(strtolower(trim($email)))."?size=$size&default=$default";
        }
        if ($full_protocol) {
            $url = 'http'.(waRequest::isHttps() ? 's' : '').':'.$url;
        }

        return $url;
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
            $order['total_str'] = wa_currency_html(ifset($order, 'total', 0), ifset($order, 'currency', null));
            if (!empty($order['create_datetime'])) {
                $order['create_datetime_str'] = wa_date('humandatetime', $order['create_datetime']);
            }
            $state = (isset($order['state_id']) && isset($states[$order['state_id']])) ? $states[$order['state_id']] : null;

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

                // Order delivery interval
                list($date, $time_from, $time_to) = shopHelper::getOrderShippingInterval($order['params']);
                if ($date) {
                    $order['shipping_interval'] = wa_date('date', $date).' '.$time_from.'-'.$time_to;
                }

                if (array_key_exists('courier', $order)) {
                    if ($order['courier']) {
                        $order['courier_name'] = htmlspecialchars($order['courier']['name']);
                    } else {
                        $order['courier_name'] = '';
                    }
                }
            }
        }

        if ($single) {
            $orders = $orders[0];

            return $orders;
        }
    }

    /**
     * Returns shipping date set for an order and saved in its params.
     * @example list($shipping_date, $shipping_time_start, $shipping_time_end) = shopHelper::getOrderShippingInterval($order_params);
     * @param array $order_params
     * @return array
     */
    public static function getOrderShippingInterval($order_params)
    {
        $shipping_date = null;
        $shipping_time_end = null;
        $shipping_time_start = null;
        if (!empty($order_params['shipping_start_datetime']) && !empty($order_params['shipping_end_datetime'])) {
            @list($shipping_date, $shipping_time_start) = explode(' ', $order_params['shipping_start_datetime'], 2);
            @list($_, $shipping_time_end) = explode(' ', $order_params['shipping_end_datetime'], 2);
            $shipping_time_start = preg_replace('~(\d\d:\d\d):\d\d~', '$1', $shipping_time_start);
            $shipping_time_end = preg_replace('~(\d\d:\d\d):\d\d~', '$1', $shipping_time_end);
        }

        return array($shipping_date, $shipping_time_start, $shipping_time_end);
    }

    public static function getOrderCustomerDeliveryTime($order_params)
    {
        $customer_delivery_date = null;
        if (!empty($order_params['shipping_params_desired_delivery.date'])) {
            if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $order_params['shipping_params_desired_delivery.date'])) {
                $customer_delivery_date = $order_params['shipping_params_desired_delivery.date'];
            }
        }

        $customer_delivery_time = null;
        if (!empty($order_params['shipping_params_desired_delivery.interval'])) {
            if (preg_match('~^\d\d?:\d\d-\d\d?:\d\d$~', $order_params['shipping_params_desired_delivery.interval'])) {
                list($from_hours, $from_minutes, $to_hours, $to_minutes) = preg_split('~:|-~', $order_params['shipping_params_desired_delivery.interval']);
                $customer_delivery_time = compact('from_hours', 'from_minutes', 'to_hours', 'to_minutes');
            }
        }

        return array($customer_delivery_date, $customer_delivery_time);
    }

    /**
     * Returns customer address data for specified order.
     *
     * @param array $order_params Array of order address parameters with keys of the form 'shipping_address.***' or 'payment_address.***'
     * @param string $addr_type Address type: 'shipping' or 'payment'
     * @return array
     * @throws waException
     */
    public static function getOrderAddress($order_params, $addr_type)
    {
        $address = array();
        $contact_fields = waContactFields::get('address');
        if ($contact_fields) {
            foreach ($contact_fields->getFields() as $k => $v) {
                $address[$k] = ifset($order_params[$addr_type.'_address.'.$k]);
            }

            return $address;
        } else {
            throw new waException('Contact fields "address" is disabled');
        }
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
        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();

        return str_replace('{$order.id}', $id, $config->getOrderFormat());
    }

    /**
     * Returns 'clean' order id without custom format applied which is specified in store settings.
     *
     * @param string $id
     * @return string Decoded order id, or empty string on failure
     */
    public static function decodeOrderId($id)
    {
        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();
        $format = $config->getOrderFormat();
        $format = '/^'.str_replace('\{\$order\.id\}', '(\d+)', preg_quote($format, '/')).'$/';
        if (preg_match($format, $id, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * List of stocks (both virtual and non-virtual).
     *
     * $frontend_stocks == false (default):
     *      Returns all stocks, including non-public. Useful for backend.
     * $frontend_stocks == true:
     *      When in frontend, returns list of stocks enabled for current settlement.
     *      When in backend, returns all public stocks.
     * $frontend_stocks is an array:
     *      A list of stock_ids. Integer stock_ids are non-virtual.
     *      Virtual stock_ids are strings 'v<integer>'.
     *
     * Returns array of stocks as rows from shop_stock/shop_virtualstock table.
     * Virtual stocks have additional 'substocks' key that lists stock_ids
     * of non-virtual stocks included.
     *
     * Caches all SQLs in memory and hence safe to use many times in one page load.
     *
     * @param mixed $frontend_stocks
     * @return array
     */
    public static function getStocks($frontend_stocks = false)
    {
        static $cache_all = null;
        if ($cache_all === null) {

            // Fetch stocks info
            $stock_model = new shopStockModel();
            $virtualstock_model = new shopVirtualstockModel();
            $stocks = (array)$stock_model->getAll('id');
            $virtual_stocks = (array)$virtualstock_model->getAll('id');

            // Fetch list of substocks for virtual stocks
            if ($virtual_stocks) {
                foreach ($virtual_stocks as $id => $s) {
                    $virtual_stocks[$id]['substocks'] = array();
                }
                $virtualstock_stocks_model = new shopVirtualstockStocksModel();
                $rows = $virtualstock_stocks_model->where('virtualstock_id IN (?)', array_keys($virtual_stocks))->order('sort')->fetchAll();
                foreach ($rows as $row) {
                    $virtual_stocks[$row['virtualstock_id']]['substocks'][] = $row['stock_id'];
                }
            }

            // Order by 'sort'
            $all_stocks = array_merge(array_values($stocks), array_values($virtual_stocks));
            usort($all_stocks, wa_lambda('$a, $b', 'return ((int) ($a["sort"] > $b["sort"])) - ((int) ($a["sort"] < $b["sort"]));'));

            // Index resulting array by id
            $cache_all = array();
            foreach ($all_stocks as $s) {
                if (isset($s['substocks'])) {
                    $cache_all['v'.$s['id']] = $s;
                } else {
                    $cache_all[$s['id']] = $s;
                }
            }
        }

        // Fetch list of frontend stock ids from routing params
        if ($frontend_stocks && !is_array($frontend_stocks) && wa()->getEnv() == 'frontend') {
            $frontend_stocks = waRequest::param('public_stocks');
            if (empty($frontend_stocks) && !is_array($frontend_stocks)) {
                $frontend_stocks = true;
            }
        }

        // Filter $cache_all to return what's requested
        if ($frontend_stocks && is_array($frontend_stocks)) {
            return array_intersect_key($cache_all, array_flip($frontend_stocks));
        } elseif ($frontend_stocks) {
            return array_filter($cache_all, wa_lambda('$a', 'return !empty($a["public"]);'));
        } else {
            return $cache_all;
        }
    }

    /**
     * @param $sku_stock array stock_id => count (for real stocks)
     * @return array stock_id => count (for both real and virtual stocks; virtual stock_id keys are strings prefixed with 'v')
     */
    public static function fillVirtulStock($sku_stock)
    {
        $result = array_map('intval', $sku_stock);
        foreach (shopHelper::getStocks() as $virtualstock_id => $s) {
            if (isset($s['substocks'])) {
                $result[$virtualstock_id] = 0;
                foreach ($s['substocks'] as $substock_id) {
                    if (!isset($sku_stock[$substock_id])) {
                        $result[$virtualstock_id] = null;
                        break;
                    } else {
                        $result[$virtualstock_id] += $sku_stock[$substock_id];
                    }
                }
            } elseif (!isset($result[$s['id']])) {
                $result[$s['id']] = null;
            }
        }

        return $result;
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
                    'low_count'      => shopStockModel::LOW_DEFAULT,
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
                $icon .= "<span class='small s-stock-left-text $warn'>"._w('%d left', '%d left', $count)."</span>";
            }
        }

        return $icon;
    }

    /**
     * Returns instance of class waContactForm.
     *
     * @param int|waContact|null $id Optional id of contact or contact object whose data must be pre-filled in contact form.
     * @param bool $ensure_address Whether address fields must be included regardless of store's contact fields settings.
     * @param bool $checkout
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

        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();

        $settings = $config->getCheckoutSettings();
        if (!isset($settings['contactinfo'])) {
            $settings = $config->getCheckoutSettings(true);
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
                // We want shipping address to show even if disabled in settings,
                // and show all address subfields regardless of frontend checkout settings.
                $fields_config['address.shipping'] = $address_config;

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
                    $fields_config[$fid]['fields']['country']['value'] = $config->getGeneralSettings('country');
                }
            }
        }

        if ($checkout && !wa()->getUser()->isAuth()) {
            $form = shopContactForm::loadConfig(
                $fields_config,
                array(
                    'namespace' => 'customer',
                )
            );

        } else {
            $form = waContactForm::loadConfig(
                $fields_config,
                array(
                    'namespace' => 'customer',
                )
            );
        }
        if ($contact) {
            $form->setValue($contact);
        }

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
     * @deprecated Shop app no longer considers access rights to other applications.
     * @param int|null $contact_id Contact id. If not specified, access rights to all contacts are verified.
     * @return bool
     */
    public static function getContactRights($contact_id = null)
    {
        return true;
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
     * @param int $counter
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

    public static function getPromoImageUrl($id, $ext, $size = null)
    {
        $v = @filemtime(wa('shop')->getDataPath('promos/'.$id.'.'.$ext, true));

        if ($params = array_filter(compact('v'))) {
            $params = '?'.http_build_query($params);
        } else {
            $params = '';
        }

        if ($size) {
            $name = sprintf('%s.%s.%s', $id, $size, $ext);
        } else {
            $name = sprintf('%s.%s', $id, $ext);
        }

        return wa('shop')->getDataUrl('promos/'.$name, true).$params;
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
            $store_email = ifset($row, 'value', '');
        }
        if (empty($store_email)) {

            /** @var shopConfig $config */
            $config = wa('shop')->getConfig();
            $store_email = $config->getGeneralSettings('email');
        }

        return $store_email;
    }

    public static function getStorefronts($verbose = false)
    {
        $storefronts = array();
        $idna = new waIdna();
        foreach (wa()->getRouting()->getByApp('shop') as $domain => $domain_routes) {
            foreach ($domain_routes as $route) {
                $url = rtrim($domain.'/'.$route['url'], '/*');
                if (strpos($url, '/') !== false) {
                    $url .= '/';
                }
                if ($verbose) {
                    $storefronts[] = array(
                        'domain'      => $domain,
                        'route'       => $route,
                        'url'         => $url,
                        'url_decoded' => $idna->decode($url),
                    );
                } else {
                    $storefronts[] = $url;
                }
            }
        }

        return $storefronts;
    }

    public static function getStorefrontCheckoutHash($storefront)
    {
        $storefronts = shopHelper::getStorefronts(true);

        $hash = false;
        foreach ($storefronts as $route) {
            if ($route['url'] === $storefront) {
                $checkout_version = ifset($route, 'route', 'checkout_version', false);
                if ($checkout_version == 2) {
                    $hash = ifset($route, 'route', 'checkout_storefront_id', false);
                    if ($hash === false) {
                        throw new waException('Storefront id not found in config');
                    }
                }
                break;
            }
        }

        return $hash;
    }

    public static function generateSignupUrl($customer, $order_storefront)
    {
        $signup_url = '';
        if (wa_is_int($customer)) {
            $customer = new shopCustomer($customer);
        } elseif (!($customer instanceof waContact)) {
            $customer = new shopCustomer(0);
        }

        /** @var shopConfig $config */
        $config = wa('shop')->getConfig();
        $guest_checkout = $config->getGeneralSettings('guest_checkout');
        if ($guest_checkout === 'merge_email' && strlen($customer['password']) <= 0 && $customer['is_user'] >= 0) {
            $order_domain = self::getDomainByStorefront($order_storefront);
            $auth = wa()->getAuthConfig();
            $auth_app = ifset($auth['app'], '');
            $signup_url = wa()->getRouteUrl($auth_app.'/signup', array(), true, $order_domain);

            if ($signup_url && $auth_app === 'shop') {
                $hash = md5(uniqid($order_storefront.$customer->getId(), true));
                $hash = substr($hash, 0, 16).$customer->getId().substr($hash, 16);
                if (substr($signup_url, 0, -1) === '?') {
                    $signup_url .= '&';
                } else {
                    $signup_url .= '?';
                }
                $signup_url .= "prefilling={$hash}";
            }
        }

        return $signup_url;
    }

    public static function getDomainByStorefront($storefront)
    {
        $domain = null;
        $storefronts = shopHelper::getStorefronts(true);
        foreach ($storefronts as $st) {
            if (trim($st['url'], '/') === trim($storefront, '/')) {
                $domain = $st['domain'];
                break;
            }
        }

        return $domain;
    }

    public static function isProductUrlInUse($product)
    {
        $col = new shopProductsCollection("search/url={$product['url']}&id!={$product['id']}");
        $count = $col->count();
        if ($count <= 0) {
            return '';
        }
        $found_products = $col->getProducts('id,name', 0, 1);
        $found_product = reset($found_products);
        $template = _w('The URL <strong>:url</strong> is already in use by another product (<a href="?action=products#/product/:another_product_id/" target="_blank" class="bold">:another_product_name</a>). You may still save this product with the same URL, but the storefront will display only one (any) product by this URL.');

        return str_replace(
            array(':url', ':another_product_id', ':another_product_name'),
            array($product['url'], $found_product['id'], htmlspecialchars($found_product['name'])),
            $template
        );
    }

    /**
     * json_decode has problems with big integers in some platforms.
     * @see discussion here http://stackoverflow.com/questions/19520487/json-bigint-as-string-removed-in-php-5-5
     * @param $input
     * @param bool $assoc
     * @return mixed
     */
    public static function jsonDecode($input, $assoc = false)
    {
        if (version_compare(PHP_VERSION, '5.4.0', '>=') && !(defined('JSON_C_VERSION') && PHP_INT_SIZE > 4)) {
            /** In PHP >=5.4.0, json_decode() accepts an options parameter, that allows you
             * to specify that large ints (like Steam Transaction IDs) should be treated as
             * strings, rather than the PHP default behaviour of converting them to floats.
             */
            $obj = json_decode($input, $assoc, 512, JSON_BIGINT_AS_STRING);
        } else {
            /** Not all servers will support that, however, so for older versions we must
             * manually detect large ints in the JSON string and quote them (thus converting
             *them to strings) before decoding, hence the preg_replace() call.
             */
            $max_int_length = strlen((string)PHP_INT_MAX) - 1;
            $json_without_bigints = preg_replace('/(:|,|\[|^)\s*(-?\d{'.$max_int_length.',})/', '$1"$2"', $input);
            $obj = json_decode($json_without_bigints, $assoc);
        }

        return $obj;

    }

    private static function getEnv($name)
    {
        static $env = array();
        if (!isset($env[$name])) {
            switch ($name) {
                case 'weight':
                    $env[$name] = shopDimension::getInstance()->getDimension('weight');
                    break;
                case 'length':
                    $env[$name] = shopDimension::getInstance()->getDimension('length');
                    break;
                case 'currencies':
                    $config = wa('shop')->getConfig();
                    /**
                     * @var shopConfig $config
                     */
                    $env[$name] = $config->getCurrencies();
                    break;
                case 'currency':
                    $config = wa('shop')->getConfig();
                    /**
                     * @var shopConfig $config
                     */
                    $env[$name] = $config->getCurrency(false);
                    break;
                case 'default_currency':
                    $config = wa('shop')->getConfig();
                    /**
                     * @var shopConfig $config
                     */
                    $env[$name] = $config->getCurrency(true);
                    break;
            }
        }

        return $env[$name];
    }

    /**
     * @param mixed $value
     * @param string $type One of price, weight or length
     * @param $target
     * @param string $from
     * @return mixed
     * @throws waException
     */
    public static function workupValue($value, $type, $target, $from = null)
    {
        switch ($type) {
            case 'price':
                if ($value) {
                    $currencies = self::getEnv('currencies');
                    $currency = $target;
                    if (isset($currencies[$currency])) {
                        if ($from) {
                            $current_currency = $from;
                        } else {
                            $current_currency = self::getEnv('currency');
                        }

                        if ($currency != $current_currency) {
                            $value = shop_currency($value, $current_currency, $currency, false);
                        } elseif (($info = waCurrency::getInfo($currency)) && isset($info['precision'])) {
                            $value = round($value, $info['precision']);
                        }
                    } else {
                        throw new waException(sprintf('Unknown currency "%s"', $currency));
                    }
                }
                break;
            case 'weight':
                if ($value) {
                    $weight_unit = $target;
                    if ($weight_unit) {
                        $weight = self::getEnv('weight');
                        if ($weight_unit != $weight['base_unit']) {
                            if (isset($weight['units'][$weight_unit])) {
                                $value = $value / $weight['units'][$weight_unit]['multiplier'];
                            } else {
                                throw new waException(sprintf('Invalid weight unit "%s"', $weight_unit));
                            }
                        }
                    }
                }
                break;
            case 'length':
                if ($value) {
                    $length_unit = $target;
                    $length = self::getEnv('length');
                    if ($length_unit != $length['base_unit']) {
                        if (isset($length['units'][$length_unit])) {
                            $value = $value / $length['units'][$length_unit]['multiplier'];
                        } else {
                            throw new waException(sprintf('Invalid length unit "%s"', $length_unit));
                        }
                    }
                }
                break;
        }

        return $value;
    }

    public static function workupOrderItems($order_items, $options)
    {
        $options += array(
            'weight'   => null,
            'tax'      => null,
            'currency' => ifset($options['order_currency']),
        );
        $items = array();

        $values = array();

        if ($options['weight']) {
            $product_ids = array();
            foreach ($order_items as $i) {
                if (!empty($i['product_id'])) {
                    $product_ids[] = $i['product_id'];
                }
            }
            $product_ids = array_unique($product_ids);
            if ($product_ids) {
                $feature_model = new shopFeatureModel();
                $feature = $feature_model->getByCode('weight');
                if ($feature) {
                    $values_model = $feature_model->getValuesModel($feature['type']);
                    $values = $values_model->getProductValues($product_ids, $feature['id']);
                }
            }
        }

        foreach ($order_items as $item) {

            $item['price'] = ifempty($item['price'], 0.0);
            $item['price'] = shopHelper::workupValue($item['price'], 'price', $options['currency'], $options['order_currency']);

            $item['total_discount'] = ifempty($item['total_discount'], 0.0);
            $item['total_discount'] = shopHelper::workupValue($item['total_discount'], 'price', $options['currency'], $options['order_currency']);

            if ($options['weight']) {
                if (empty($item['weight'])) {
                    $item['weight'] = null;
                    if (ifset($item['type']) == 'product') {
                        if (!empty($item['sku_id']) && isset($values['skus'][$item['sku_id']])) {
                            $item['weight'] = $values['skus'][$item['sku_id']];
                        } elseif (!empty($item['product_id']) && isset($values[$item['product_id']])) {
                            $item['weight'] = $values[$item['product_id']];
                        }
                    }
                }

                $item['weight'] = shopHelper::workupValue($item['weight'], 'weight', $options['weight']);
            }

            $items[] = array(
                'id'             => ifset($item['id']),
                'name'           => ifset($item['name']),
                'sku'            => ifset($item['sku_code']),
                'tax_rate'       => ifset($item['tax_percent']),
                'tax_included'   => ifset($item['tax_included'], 1),
                'description'    => '',
                'price'          => (float)$item['price'],
                'quantity'       => (int)ifset($item['quantity'], 0),
                'total'          => (float)$item['price'] * (int)$item['quantity'],
                'type'           => ifset($item['type'], 'product'),
                'product_id'     => ifset($item['product_id']),
                'weight'         => (float)ifset($item['weight']),
                'weight_unit'    => (string)$options['weight'],
                'total_discount' => (float)$item['total_discount'],
                'discount'       => (float)($item['quantity'] ? ($item['total_discount'] / $item['quantity']) : 0.0),
            );


        }

        return array_values($items);
    }

    /**
     * @param mixed[] $order
     * @param mixed [string] $options
     * @return waOrder
     */
    public static function getWaOrder($order, $options = array())
    {
        $empty_address = array(
            'firstname' => '',
            'lastname'  => '',
            'country'   => '',
            'region'    => '',
            'city'      => '',
            'street'    => '',
            'zip'       => '',
        );

        $order += array(
            'shipping' => 0.0,
            'discount' => 0.0,
            'tax'      => 0.0,
            'params'   => array(),
        );

        $default_currency = self::getEnv('default_currency');

        $options += array(
            'currency' => ifset($order['currency'], $default_currency),
        );

        $shipping_address = array_merge($empty_address, shopHelper::getOrderAddress($order['params'], 'shipping'));
        $billing_address = array_merge($empty_address, shopHelper::getOrderAddress($order['params'], 'billing'));
        if (!count(array_filter($billing_address, 'strlen'))) {
            $billing_address = $shipping_address;
        }

        if (($options['currency'] != $order['currency']) && !empty($order['rate'])) {

            $rate = $order['rate'];
            $target_currency = $default_currency;

            if (!empty($order['params']['shipping_currency_rate'])
                &&
                intval($order['params']['shipping_currency_rate'])
                &&
                !empty($order['params']['shipping_currency'])
                &&
                ($order['params']['shipping_currency'] == $options['currency'])
            ) {
                $rate = $rate / $order['params']['shipping_currency_rate'];
                $target_currency = $order['params']['shipping_currency'];
            }

            $order['tax'] = $order['tax'] * $rate;
            $order['shipping'] = $order['shipping'] * $rate;
            $order['discount'] = $order['discount'] * $rate;
            $order['total'] = $order['total'] * $rate;

            foreach ($order['items'] as &$item) {
                $item['price'] = ifempty($item['price'], 0.0) * $rate;
                $item['total_discount'] = ifempty($item['total_discount'], 0.0) * $rate;
                unset($item);
            }

            $order['original_currency'] = $order['currency'];
            $order['currency'] = $target_currency;
        }

        if (isset($options['discount'])) {

            $items_total_discount = 0.0;
            $items_total = 0.0;

            $map = array();

            foreach ($order['items'] as $item_id => &$item) {
                if (isset($item['total_discount'])) {
                    $items_total_discount += self::workupValue($item['total_discount'], 'price', $order['currency'], $order['currency']);
                }
                $map[$item_id] = $item['price'];
                $items_total += $item['price'] * $item['quantity'];
                unset($item);
            }

            asort($map, SORT_NUMERIC);

            #correct items prices & discount
            $order['discount'] = self::workupValue($order['discount'], 'price', $order['currency'], $order['currency']);
            $items_total_discount = self::workupValue($items_total_discount, 'price', $order['currency'], $order['currency']);
            if ($order['discount'] != $items_total_discount) {
                $discount = $order['discount'];// - $items_total_discount;
                $discount_rate = ($items_total > 0) ? min(1.0, ($discount / $items_total)) : 0;
                $n = count($order['items']);

                $_delta = $order['discount'] - $items_total_discount;
                if (waSystemConfig::isDebug()) {
                    $order_discount = $order['discount'];
                    $id = ifset($order['id'], '-');
                    $_debug = compact('id', 'items_total_discount', 'order_discount', '_delta');
                    waLog::log(var_export($_debug, true), 'shop/round_discount.error.log');
                }

                foreach ($map as $item_id => $_price) {
                    $item = &$order['items'][$item_id];
                    --$n;
                    $item_discount = self::workupValue($item['price'] * $discount_rate, 'price', $order['currency'], $order['currency']);
                    $item_total_discount = ($item_discount * $item['quantity']);
                    if (!$n && (($item_total_discount > $discount) || ($item_total_discount < $discount))) {
                        $item_discount = self::workupValue($discount / $item['quantity'], 'price', $order['currency'], $order['currency']);

                        $_error = abs($item_total_discount - $discount);
                        if (($_error > 1) || waSystemConfig::isDebug()) {
                            $id = ifset($order['id'], '-');
                            $_debug = compact('id', 'item_total_discount', 'discount', 'item_discount', '_error');
                            waLog::log(var_export($_debug, true), 'shop/round_discount.error.log');
                        }
                        $discount = 0;
                    } else {
                        $discount -= ($item_discount * $item['quantity']);
                    }
                    if (empty($options['discount'])) {
                        $item['price'] -= $item_discount;
                        $item['total_discount'] = 0;
                    } else {
                        $item['total_discount'] = $item_discount * $item['quantity'];
                    }

                    unset($item);
                }

                $order['discount'] = $discount;
            }
        }

        $tax_included = null;
        foreach ($order['items'] as $item_id => $item) {
            if (isset($item['tax_included'])) {
                if ($tax_included !== null) {
                    if ($tax_included != !!$item['tax_included']) {
                        $tax_included = 0;
                    }
                } else {
                    $tax_included = !!$item['tax_included'];
                }
            }
        }

        $item_options = array(
            'order_currency'   => ifset($order['currency'], $default_currency),
            'shipping_address' => $shipping_address,
            'billing_address'  => $billing_address,
        );

        $order_data = array(
            #common data
            'id_str'                => ifempty($order['id_str'], $order['id']),
            'id'                    => $order['id'],

            #dates
            'datetime'              => ifempty($order['create_datetime']),
            'update_datetime'       => ifempty($order['update_datetime']),
            'paid_datetime'         => empty($order['paid_date']) ? null : ($order['paid_date'].' 00:00:00'),

            #contact data
            'contact_id'            => $order['contact_id'],

            #finance data
            'currency'              => $options['currency'],
            'total'                 => self::workupValue($order['total'], 'price', $options['currency'], $order['currency']),
            'discount'              => self::workupValue($order['discount'], 'price', $options['currency'], $order['currency']),
            'tax'                   => self::workupValue($order['tax'], 'price', $options['currency'], $order['currency']),
            'shipping'              => self::workupValue($order['shipping'], 'price', $options['currency'], $order['currency']),

            #billing data
            'payment_name'          => ifset($order['params']['payment_name'], ''),
            'billing_address'       => $billing_address,

            #shipping data
            'shipping_name'         => ifset($order['params']['shipping_name'], ''),
            'shipping_address'      => $shipping_address,

            #shipping tax data
            'shipping_tax_rate'     => ifset($order['params']['shipping_tax_percent']),
            'shipping_tax_included' => ifset($order['params']['shipping_tax_included'], true),

            #content data
            'items'                 => self::workupOrderItems($order['items'], $item_options + $options),

            #describe it
            'comment'               => ifempty($order['comment'], ''),
            'description'           => sprintf(_w('Payment for order %s'), ifempty($order['id_str'], $order['id'])),

            #extra data
            'params'                => ifempty($order['params'], array()),
        );

        if (!empty($options['weight'])) {
            $order_data['weight_unit'] = $options['weight'];
        }

        if (!empty($options['dimensions'])) {
            $order_data['dimensions_unit'] = $options['dimensions'];
        }

        if (in_array($tax_included, array(true, false), true)) {
            $order_data['tax_included'] = $tax_included;
        }

        if (isset($order['params']['shipping_tax_percent'])) {
            $order_data['shipping_tax_rate'] = $order['params']['shipping_tax_percent'];
        }

        $total = $order_data['shipping'];
        $total -= $order_data['discount'];
        foreach ($order_data['items'] as $item) {
            $total += ($item['price'] * $item['quantity']);
        }

        if ($total !== $order_data['total']) {
            if ($options['currency'] != ifset($order['original_currency'], $order['currency'])) {
                $_error = abs($order_data['total'] - $total);
                if (($_error > 1) || waSystemConfig::isDebug()) {
                    $id = ifset($order['id'], '-');
                    $_debug = compact('id', 'order_data', '_error');
                    waLog::log(var_export($_debug, true), 'shop/round_total.error.log');
                }
                $order_data['total'] = $total;
            }
        }

        return waOrder::factory($order_data);
    }
}
