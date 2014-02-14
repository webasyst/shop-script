<?php

class shopConfig extends waAppConfig
{
    protected $_routes = array();
    protected $image_sizes = array(
        'big'        => '970',
        'default'    => '750x0',
        'thumb'      => '200x0',
        'crop'       => '96x96',
        'crop_small' => '48x48'
    );


    public function checkRights($module, $action)
    {
        if ($module == 'frontend' && waRequest::param('ssl') &&
            (strpos($action, 'my') === 0 || $action === 'checkout')) {
            $is_https = waRequest::server('HTTPS') && (strtolower(waRequest::server('HTTPS')) !== 'off');
            if (!$is_https) {
                $url = 'https://'.waRequest::server('HTTP_HOST').wa()->getConfig()->getCurrentUrl();
                wa()->getResponse()->redirect($url, 301);
            }
        }
        return true;
    }

    public function getImageSize($name)
    {
        return isset($this->image_sizes[$name]) ? $this->image_sizes[$name] : null;
    }

    public function getImageSizes($type = 'all')
    {
        if ($type == 'system') {
            return $this->image_sizes;
        }
        $custom_sizes = $this->getOption('image_sizes');
        if ($type == 'custom') {
            return $custom_sizes;
        }
        $sizes = array_merge(array_values($this->image_sizes), array_values($custom_sizes));
        return array_unique($sizes);
    }

    public function getLastDatetime()
    {
        $storage = wa()->getStorage();
        $shop_last_datetime = $storage->get('shop_last_datetime');
        if (!$shop_last_datetime) {
            $contact_model = new waContactSettingsModel();
            $shop_last_datetime = (int)$contact_model->getOne(wa()->getUser()->getId(), 'shop', 'shop_last_datetime');
            if (!$shop_last_datetime) {
                $shop_last_datetime = time();
            }
            $contact_model->set(wa()->getUser()->getId(), 'shop', 'shop_last_datetime', time());
            $storage->set('shop_last_datetime', $shop_last_datetime);
        }
        return $shop_last_datetime;
    }

    public function onCount()
    {
        if (!wa()->getUser()->getRights('shop', 'orders')) {
            return null;
        }
        $order_model = new shopOrderModel();
        return $order_model->getStateCounters('new');
    }

    public function getRouting($route = array(), $dispatch = false)
    {
        $url_type = isset($route['url_type']) ? $route['url_type'] : 0;
        if (!isset($this->_routes[$url_type]) || $dispatch) {
            $routes = parent::getRouting($route);
            if ($routes) {
                if (isset($routes[$url_type])) {
                    $routes = $routes[$url_type];
                } else {
                    $routes = $routes[0];
                }
            }
            // for URL <category_url>/<product_url>/
            if ($dispatch && $url_type == 2) {
                $category_model = new shopCategoryModel();
                $categories = $category_model->getByRoute(wa()->getRouting()->getDomain(null, true).'/'.$route['url']);
                $categories_routes = array();
                foreach ($categories as $c) {
                    $categories_routes[$c['full_url'].'/'] = array(
                        'module'      => 'frontend',
                        'action'      => 'category',
                        'category_id' => $c['id'],
                    );
                }
                $routes = array_merge($categories_routes, $routes);
            }
            /**
             * Extend routing via plugin routes
             * @event routing
             * @param array $routes
             * @return array $routes routes collected for every plugin
             */
            $result = wa()->event(array($this->application, 'routing'), $route);
            $all_plugins_routes = array();
            foreach ($result as $plugin_id => $routing_rules) {
                if ($routing_rules) {
                    $plugin = str_replace('-plugin', '', $plugin_id);
                    /*
                     if ($url_type == 0) {
                     $routing_rules = $routing_rules[0];
                     } else {
                     $routing_rules = $routing_rules[1];
                     }
                     */
                    foreach ($routing_rules as $url => & $route) {
                        if (!is_array($route)) {
                            list($route_ar['module'], $route_ar['action']) = explode('/', $route);
                            $route = $route_ar;
                        }
                        $route['plugin'] = $plugin;
                        $all_plugins_routes[$url] = $route;
                    }
                    unset($route);
                }
            }
            $routes = array_merge($all_plugins_routes, $routes);
            if ($dispatch) {
                return $routes;
            }
            $this->_routes[$url_type] = $routes;
        }
        return $this->_routes[$url_type];
    }

    public function getCurrency($settings_only = true)
    {
        $c = null;
        if (!$settings_only) {
            if ($this->environment == 'frontend') {
                if (wa()->getStorage()->get('shop/currency')) {
                    $c = wa()->getStorage()->get('shop/currency');
                } elseif (waRequest::param('currency')) {
                    $c = waRequest::param('currency');
                }
            }
        }

        if ($c && !$this->getCurrencies($c)) {
            $c = wa()->getSetting('currency', 'USD', 'shop');
            if ($this->getEnvironment() == 'frontend') {
                wa()->getStorage()->remove('shop/currency');
                wa()->getStorage()->remove('shop/cart');
            }
        } elseif (!$c) {
            $c = wa()->getSetting('currency', 'USD', 'shop');
        }
        return $c;
    }

    public function getCurrencies($codes = null)
    {
        $model = new shopCurrencyModel();
        return $model->getCurrencies($codes);
    }

    public function getOrderFormat()
    {
        return wa()->getSetting('order_format', '#100{$order.id}', 'shop');
    }

    public function setCurrency($currency)
    {
        $model = new waAppSettingsModel();
        $model->set('shop', 'currency', $currency);
    }

    public function getGeneralSettings($field = null)
    {
        static $settings = array();
        if (!$settings) {
            $all_settings = wa()->getSetting(null, '', 'shop');
            foreach (array(
                         'name'                  => wa()->accountName(),
                         'email'                 => wa()->getSetting('email', '', 'webasyst'),
                         'phone'                 => '+1 (212) 555-1234',
                         'country'               => '',
                         'order_format'          => $this->getOrderFormat(),
                         'use_gravatar'          => 1,
                         'gravatar_default'      => 'custom',
                         'require_captcha'       => 1, // is captcha is required for add reviews
                         'require_authorization' => 0 // is authorization is required for add reviews
                     ) as $k => $value) {
                $settings[$k] = isset($all_settings[$k]) ? $all_settings[$k] : $value;
            }
        }
        if ($field) {
            if (isset($settings[$field])) {
                return $settings[$field];
            } else {
                return wa()->getSetting($field, null, 'shop');
            }
        } else {
            return $settings;
        }
    }

    public function getSidebarWidth()
    {
        $settings_model = new waContactSettingsModel();
        $width = (int)$settings_model->getOne(
            wa()->getUser()->getId(),
            'shop',
            'sidebar_width'
        );
        if (!$width) {
            return 250;
        }
        return max(min($width, 400), 200);
    }

    public function setSidebarWidth($width)
    {
        $width = max(min((int)$width, 400), 200);
        $settings_model = new waContactSettingsModel();
        $settings_model->set(
            wa()->getUser()->getId(),
            'shop',
            'sidebar_width',
            $width
        );
    }

    /**
     * @param bool $all - return all available or only enabled steps
     * @return array
     */
    public function getCheckoutSettings($all = false)
    {
        $all_steps = include(wa('shop')->getConfig()->getAppPath('lib/config/data/checkout.php'));
        // @todo: event to get all available steps from plugins
        if ($all) {
            return $all_steps;
        }
        $file = wa('shop')->getConfig()->getConfigPath('checkout.php', true, 'shop');
        if (file_exists($file) && is_array($steps = include($file))) {
            foreach ($steps as $step_id => & $step) {
                if (is_array($step)) {
                    $step = $step + $all_steps[$step_id];
                } elseif ($step) {
                    $step = $all_steps[$step_id];
                } else {
                    unset($steps[$step_id]);
                }
            }
        } else {
            $steps = $all_steps;
        }
        $plugin_model = new shopPluginModel();
        if (!$plugin_model->countByField('type', 'shipping') && isset($steps['shipping'])) {
            unset($steps['shipping']);
        }
        if (!$plugin_model->countByField('type', 'payment') && isset($steps['payment'])) {
            unset($steps['payment']);
        }
        reset($steps);
        return $steps;
    }
    
    public function getSaveQuality() {
        $quality = $this->getOption('image_save_quality');
        if(!$quality) {
            $quality = 90;
        }
        return $quality;
    }

}

function shop_currency($n, $in_currency = null, $out_currency = null, $format = true)
{
    /**
     * @var shopConfig $config
     */
    $config = wa('shop')->getConfig();
    $primary = $config->getCurrency(true);
    // current currency
    $currency = $config->getCurrency(false);
    if (!$in_currency) {
        $in_currency = $primary;
    }
    if ($in_currency === true || $in_currency === 1) {
        $in_currency = $currency;
    }
    if (!$out_currency) {
        $out_currency = $currency;
    }

    if ($in_currency != $out_currency) {
        $currencies = wa('shop')->getConfig()->getCurrencies(array($in_currency, $out_currency));
        if (isset($currencies[$in_currency]) && $in_currency != $primary) {
            $n = $n * $currencies[$in_currency]['rate'];
        }
        if ($out_currency != $primary) {
            $n = $n / ifempty($currencies[$out_currency]['rate'], 1.0);
        }
    }
    if ($format === 'h') {
        return wa_currency_html($n, $out_currency);
    } elseif ($format) {
        return wa_currency($n, $out_currency);
    } else {
        return str_replace(',', '.', $n);
    }

}

function shop_currency_html($n, $in_currency = null, $out_currency = null, $format = 'h')
{
    return shop_currency($n, $in_currency, $out_currency, $format);
}