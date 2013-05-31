<?php
class shopHelper
{
    /**
     *
     * Get HTML icon view
     * @param string $icon type
     * @param string $default icon type
     * @param int $size 10|16
     * @return string HTML
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

    public static function getPaymentMethods($order = array())
    {
        $plugin_model = new shopPluginModel();
        $methods = $plugin_model->listPlugins(shopPluginModel::TYPE_PAYMENT);
        $order_params = $order ? $order['params'] : array();
        $order = new waOrder(array(
            'contact_id' => $order ? $order['contact_id'] : null,
            'contact' => $order ? new waContact($order['contact_id']) : null,
            'params' => $order_params
        ));
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
                    if ($order_params && $m['id'] == $order_params['payment_id'] && isset($order_params['payment_params_'.$name])) {
                        $row['value'] = $order_params['payment_params_'.$name];
                    }
                    $controls[$name] = waHtmlControl::getControl($row['control_type'], $name, $row);
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

    public static function getShippingMethods($address = null, $items = array(), $params = array())
    {
        $plugin_model = new shopPluginModel();
        $methods = $plugin_model->listPlugins(shopPluginModel::TYPE_SHIPPING);
        if ($address !== null) {
            $result = array();
            $currency = isset($params['currency']) ? $params['currency'] : wa()->getConfig()->getCurrency();
            $dimension = shopDimension::getInstance()->getDimension('weight');
            foreach ($methods as $m) {
                $plugin = shopShipping::getPlugin($m['plugin'], $m['id']);
                $plugin_currency = $plugin->allowedCurrency();
                $total = null;
                if (isset($params['total_price'])) {
                    if ($plugin_currency != $currency) {
                        $total = shop_currency($params['total_price'], $currency, $plugin_currency, false);
                    } else {
                        $total = $params['total_price'];
                    }
                }
                $weight_unit = $plugin->allowedWeightUnit();
                if ($weight_unit != $dimension['base_unit']) {
                    $shipping_items = array();
                    foreach ($items as $item_id => $item) {
                        if ($item['weight']) {
                            $item['weight'] = $item['weight'] * $dimension['units'][$weight_unit]['multiplier'];
                        }
                        $shipping_items[$item_id] = $item;
                    }
                } else {
                    $shipping_items = $items;
                }
                $rates = $plugin->getRates($shipping_items, $address ? $address : array(), $total ? array('total_price' => $total) : array());
                if (is_array($rates)) {
                    foreach ($rates as $rate_id => $info) {
                        if (is_array($info)) {
                            $result[$m['id'].'.'.$rate_id] = array(
                                'plugin' => $m['plugin'],
                                'name'   => $m['name'].(!empty($info['name']) ? ' ('.$info['name'].')' : ''),
                                'rate'   => (float)shop_currency(is_array($info['rate']) ? max($info['rate']) : $info['rate'],
                                    $plugin_currency, $currency, false)
                            );
                        }
                    }
                } else {
                    $result[$m['id']] = array(
                        'plugin' => $m['plugin'],
                        'name'   => $m['name'],
                        'error'  => $rates,
                        'rate'   => ''
                    );
                }
            }
            return $result;
        } else {
            return $methods;
        }
    }

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
                    $forms = $plugin->getPrintForms();
                    foreach ($forms as $id => $form) {
                        $plugins["{$type}.{$id}"] = $form;
                    }
                }
            } catch (waException $e) {
            }

            $type = 'shipping';
            $key = ifempty($order['params'][$type.'_id']);
            try {
                if (!empty($key) && ($plugin = shopShipping::getPlugin(null, $key)) && method_exists($plugin, 'getPrintForms')) {
                    $forms = $plugin->getPrintForms();
                    foreach ($forms as $id => $form) {
                        $plugins["{$type}.{$id}"] = $form;
                    }
                }
            } catch (waException $e) {
            }

            foreach ($plugins as $plugin_id => & $plugin) {
                if (strpos($plugin_id, '.')) {
                    $plugin['url'] = "?module=order&action=printform&form_id={$plugin_id}&order_id={$order['id']}";
                } else {

                    $plugin['url'] = "?plugin={$plugin_id}&module=printform&action=display&order_id={$order['id']}";
                }
            }
            unset($plugin);
        }
        //TODO separate backend & frontend
        return $plugins;
    }

    protected static $badges = array();

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
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param string $email The email address
     * @param string $size Size in pixels, defaults to 50
     * @param string $default Default imageset to use [ custom | 404 | mm | identicon | monsterid | wavatar ]
     * @param string $rating Maximum rating (inclusive) [ g | pg | r | x ]
     * @return String containing either just a URL or a complete image tag
     * @source http://gravatar.com/site/implement/images/php/
     */
    public static function getGravatar($email, $size = 50, $default = 'mm')
    {
        if ($default == 'custom') {
            $default = wa()->getRootUrl(true).'wa-content/img/userpic'.$size.'.jpg';
            $default = urlencode($default);
        }
        return 'http://www.gravatar.com/avatar/'.md5(strtolower(trim($email)))."?size=$size&default=$default";
    }

    public static function workupOrders(&$orders, $single = false)
    {
        if ($single) {
            $orders = array($orders);
        }

        $items_count = 4;
        $currency = wa('shop')->getConfig()->getCurrency();

        $workflow = new shopWorkflow();
        $states = $workflow->getAllStates();
        foreach ($orders as & $order) {
            $order['id_str'] = self::encodeOrderId($order['id']);
            $order['total_str'] = wa_currency($order['total'], $order['currency']);
            if (!empty($order['create_datetime'])) {
                $order['create_datetime_str'] = wa_date('humandatetime', $order['create_datetime']);
            }
            $state = isset($states[$order['state_id']]) ? $states[$order['state_id']] : null;

            if (!empty($order['items'])) {
                $items_str = '';
                $i = 0;
                foreach ($order['items'] as $item) {
                    if ($i >= $items_count) {
                        break;
                    }
                    $items_str .= ', '.$item['name'];
                    if ($item['type'] == 'product') {
                        $items_str .= ' x '.$item['quantity'];
                    }
                    $i += 1;
                }
                $order['items_str'] = substr($items_str, 2);
            }
            if (!$single && isset($order['items'])) {
                unset($order['items']);
            }

            $icon = '';
            $style = '';
            if ($state) {
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

    public static function getOrderAddress($order_params, $addr_type)
    {
        $address = array();
        foreach (waContactFields::get('address')->getFields() as $k => $v) {
            $address[$k] = ifset($order_params[$addr_type.'_address.'.$k]);
        }
        return $address;
    }

    public static function getShippingAddressText($order_params, $for_map = true)
    {
        $address = array();
        foreach (waContactFields::get('address')->getFields() as $k => $v) {
            $address[$k] = ifset($order_params['shipping_address.'.$k]);
        }

        if ($for_map) {
            $address_f = array();
            foreach (array('country', 'region', 'zip', 'city', 'street') as $k) {
                if (!isset($address[$k])) {
                    continue;
                } else if ($k == 'country') {
                    $address_f[$k] = waCountryModel::getInstance()->name(ifempty($address['country']));
                } else if ($k == 'region') {
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

    public static function encodeOrderId($id)
    {
        return str_replace('{$order.id}', $id, wa('shop')->getConfig()->getOrderFormat());
    }

    public static function decodeOrderId($id)
    {
        $format = wa('shop')->getConfig()->getOrderFormat();
        $format = '/^'.str_replace('\{\$order\.id\}', '(\d+)', preg_quote($format)).'$/';
        if (preg_match($format, $id, $m)) {
            return $m[1];
        }
        return '';
    }

    public static function getStockCountIcon($count, $stock_id = null, $include_text = false)
    {
        static $stocks = array();
        if (!$stocks) {
            $model = new shopStockModel();
            $stocks = $model->getAll('id');
        }
        if ($count === null) {
            $icon = "<i class='icon10 status-green' title='"._w("In stock")."'></i>";
            $warn = '';
        } else {
            if (!$stock_id || empty($stocks[$stock_id])) {
                $bounds = array(
                    'critical_count' => shopStockModel::CRITICAL_DEFAULT,
                    'low_count'      => shopStockModel::LOW_DEFAULT
                );
            } else {
                $bounds = $stocks[$stock_id];
            }
            if ($count <= $bounds['critical_count']) {
                $icon = "<i class='icon10 status-red' title='"._w("Out of stock")."'></i>";
                $warn = 's-stock-warning-none';
            } elseif ($count > $bounds['critical_count'] && $count <= $bounds['low_count']) {
                $icon = "<i class='icon10 status-yellow' title='"._w("Low stock")."'></i>";
                $warn = 's-stock-warning-low';
            } else {
                $icon = "<i class='icon10 status-green' title='"._w("In stock")."'></i>";
                $warn = '';
            }
            if ($count !== null && $include_text) {
                $icon .= "<span class='small $warn'>$count left</span>";
            }
        }
        return $icon;
    }

    public static function getCustomerForm($id = null, $ensure_address = false)
    {
        $settings = wa('shop')->getConfig()->getCheckoutSettings();
        if (!isset($settings['contactinfo'])) {
            $settings = wa('shop')->getConfig()->getCheckoutSettings(true);
        }

        $fields_config = ifset($settings['contactinfo']['fields'], array());
        $address_config = ifset($fields_config['address'], array());
        unset($fields_config['address']);

        if (wa()->getEnv() == 'backend' && !isset($fields_config['address.shipping'])) {
            $fields_config['address.shipping'] = array();
        }

        if ($ensure_address && !isset($fields_config['address.billing']) && !isset($fields_config['address.shipping'])) {
            $fields_config['address'] = $address_config;
        }

        $form = waContactForm::loadConfig($fields_config, array(
            'namespace' => 'customer'
        ));
        if ($id) {
            $contact = new waContact($id);
            $contact->getName(); // make sure contact exists; throws exception otherwise
            $form->setValue($contact);
        }
        return $form;
    }

    /**
     * Suggest url from string
     * @param string $str
     * @param boolean $strict
     * @return string
     */
    public static function transliterate($str, $strict = true)
    {
        $str = preg_replace('/\s+/', '-', $str);
        if ($str) {
            foreach (waLocale::getAll() as $lang) {
                $str = waLocale::transliterate($str, $lang);
            }
        }
        $str = preg_replace('/[^a-zA-Z0-9_-]+/', '', $str);
        if ($strict && !$str) {
            $str = date('Ymd');
        }
        return strtolower($str);
    }

    public static function getContactRights($contact_id=null)
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
}
