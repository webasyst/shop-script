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
            if (!waRequest::isHttps()) {
                $url = 'https://'.waRequest::server('HTTP_HOST').wa()->getConfig()->getCurrentUrl();
                wa()->getResponse()->redirect($url, 301);
            }
        } elseif ($module == 'order' || $module == 'orders' || $module == 'coupons' || $module == 'workflow') {
            return wa()->getUser()->getRights('shop', 'orders');
        } elseif (substr($module, 0, 7) == 'reports') {
            return wa()->getUser()->getRights('shop', 'reports');
        } elseif (substr($module, 0, 8) == 'settings') {
            return wa()->getUser()->getRights('shop', 'settings');
        } elseif (substr($module, 0, 7) == 'service') {
            return wa()->getUser()->getRights('shop', 'services');
        } elseif ($module == 'customers') {
            return wa()->getUser()->getRights('shop', 'customers');
        } elseif ($module == 'importexport' || $module == 'csv' || $module == 'images') {
            return wa()->getUser()->getRights('shop', 'importexport');
        } elseif ($module == 'promos') {
            return wa()->getUser()->getRights('shop', 'setscategories');
        }
        return true;
    }

    public function getSortOrderItemsVariants()
    {
        return [
            'user_cart' => ['name' => _w('as added to the shopping cart')],
            'sku_name'  => ['name' => _w('by item name')],
            'sku_code'  => ['name' => _w('by SKU code')],
        ];
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
            if (wa()->getUser()->getId()) {
                $contact_model->set(wa()->getUser()->getId(), 'shop', 'shop_last_datetime', time());
            }
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
                    if ($plugin == $plugin_id) {
                        // apps can not add routes to other apps
                        continue;
                    }
                    foreach ($routing_rules as $url => & $route) {
                        if (!is_array($route)) {
                            list($route_ar['module'], $route_ar['action']) = explode('/', $route);
                            $route = $route_ar;
                        }
                        $route['plugin'] = $plugin;
                        $route['app'] = $this->application;
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

    /**
     * Get current or primary currency
     * Important note: has implicit dependency on environment
     *
     * @param bool $settings_only
     *      In frontend environment
     *          if true then return current currency (storefront currency),
     *          otherwise return primary currency
     *      In other environment always return primary currency
     * @return mixed|null
     */
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
                         'name'                          => wa()->accountName(),
                         'email'                         => wa()->getSetting('email', '', 'webasyst'),
                         'phone'                         => '+1 (234) 555-1234',
                         'country'                       => '',
                         'order_format'                  => $this->getOrderFormat(),
                         'use_gravatar'                  => 1,
                         'map'                           => 'google',
                         'gravatar_default'              => 'custom',
                         'require_captcha'               => 1, // is captcha is required for add reviews
                         'require_authorization'         => 0, // is authorization is required for add reviews
                         'review_service_agreement'      => '',
                         'review_service_agreement_hint' => '',
                         'sort_order_items'              => 'user_cart',
                     ) as $k => $value) {
                $settings[$k] = isset($all_settings[$k]) ? $all_settings[$k] : $value;
            }
            if (isset($all_settings['workhours'])) {
                if ($all_settings['workhours']) {
                    $workhours = json_decode($all_settings['workhours'], true);
                    $settings['workhours'] = array(
                        'hours_from'   => $workhours['from'],
                        'hours_to'     => $workhours['to'],
                        'days'         => array(),
                        'days_from_to' => '',
                    );
                    $strings = array(
                        _ws('Sun'),
                        _ws('Mon'),
                        _ws('Tue'),
                        _ws('Wed'),
                        _ws('Thu'),
                        _ws('Fri'),
                        _ws('Sat'),
                    );
                    if ($workhours['days']) {
                        foreach ($workhours['days'] as $d) {
                            $settings['workhours']['days'][$d] = $strings[$d];
                        }

                        $settings['workhours']['days_from_to'] = self::getDaysFromTo($workhours['days'], $strings);
                    }
                } else {
                    $settings['workhours'] = $all_settings['workhours'];
                }
            } else {
                $settings['workhours'] = null;
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

    /** Helper for getGeneralSettings(). Builds a human-readable string describing work days of a shop. */
    public static function getDaysFromTo($work_days, $day_names)
    {
        if (count($work_days) < 1) {
            return '';
        }

        // First day of week is different in different custures
        $locale_info = waLocale::getInfo(wa()->getLocale());
        $first_day_of_week = ifset($locale_info['first_day'], 1) % 7;
        $last_day_of_week = ($first_day_of_week + 6) % 7;

        // Being paranoid
        $first_day_of_week = max($first_day_of_week, 0);
        $first_day_of_week = min($first_day_of_week, 6);
        $last_day_of_week = max($last_day_of_week, 0);
        $last_day_of_week = min($last_day_of_week, 6);

        // Loop through $work_days, starting from $first_day_of_week
        // Add periods of consecutive work days to $days_from_to - see self::getDaysFromToPeriod().
        $days_from_to = array();
        $first_day_to_add = null;
        $last_day_to_add = null;
        $i = $first_day_of_week;
        while (true) {
            if (in_array($i, $work_days)) {
                $last_day_to_add = $i;
                if ($first_day_to_add === null) {
                    $first_day_to_add = $i;
                }
            } else {
                if ($last_day_to_add !== null) {
                    $days_from_to[] = self::getDaysFromToPeriod($first_day_to_add, $last_day_to_add, $day_names);
                }
                $last_day_to_add = $first_day_to_add = null;
            }

            if ($i == $last_day_of_week) {
                break;
            }
            $i = ($i + 1) % 7;
        }
        if ($last_day_to_add !== null) {
            $days_from_to[] = self::getDaysFromToPeriod($first_day_to_add, $last_day_to_add, $day_names);
        }

        return join(', ', $days_from_to);
    }

    // Helper for self::getDaysFromTo()
    // Builds a string of one or more consecutive work days, e.g. "Sun-Fri", "Mon,Tue" or "Fri".
    public static function getDaysFromToPeriod($first_day_to_add, $last_day_to_add, $day_names)
    {
        // Being paranoid
        $first_day_to_add = max($first_day_to_add, 0);
        $first_day_to_add = min($first_day_to_add, 6);
        $last_day_to_add = max($last_day_to_add, 0);
        $last_day_to_add = min($last_day_to_add, 6);

        $period = array();
        $i = $first_day_to_add;
        while (true) {
            $period[] = $day_names[$i];
            if ($i == $last_day_to_add) {
                break;
            }
            $i = ($i + 1) % 7;
        }
        if (count($period) > 2) {
            return $period[0].'—'.end($period);
        } else {
            return join(', ', $period);
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
     * Return steps of old (before 8.0) checkout
     *
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
            unset($steps[shopCheckout::STEP_SHIPPING]);
        }
        if (!$plugin_model->countByField('type', 'payment') && isset($steps['payment'])) {
            unset($steps[shopCheckout::STEP_PAYMENT]);
        }
        reset($steps);
        return $steps;
    }

    public function getSaveQuality($for2x = false)
    {
        $quality = $this->getOption('image_save_quality'.($for2x ? '_2x' : ''));
        if (!$quality) {
            $quality = $for2x ? 70 : 90;
        }
        return $quality;
    }

    public function getRoundingOptions()
    {
        static $result = null;
        if ($result === null) {
            $result = wa('shop')->getConfig()->getOption('rounding_options');
            foreach ($result as &$label) {
                $label = _w($label);
            }
            unset($label);
        }
        return $result;
    }

    public function getSchedule($schedule = null)
    {
        $prepare_time = function ($value, $default) {
            $time_validator = new waTimeValidator();
            if (!$time_validator->isValid($value)) {
                return $default;
            }

            $time = $time_validator->parse($value);
            if (mb_strlen($time['hours']) === 1) {
                $time['hours'] = "0{$time['hours']}";
            }
            if (empty($time['minutes'])) {
                $time['minutes'] = '00';
            }

            return $time['hours'].':'.$time['minutes'];
        };

        // Default times
        $start_time = '00:00';
        $end_time = '23:59';
        $end_processing_time = '14:00';

        if (!$schedule || !is_array($schedule)) {
            // Load shop schedule
            $schedule = json_decode(wa()->getSetting('schedule', '{}'), true);
            $schedule = is_array($schedule) ? $schedule : [];
        }

        // Previously, the store was setting up a schedule.
        // Try to use this data if they were
        $workhours = json_decode(wa()->getSetting('workhours', '{}', 'shop'), true);
        $workhours = is_array($workhours) ? $workhours : [];

        // Week:
        $weekdays = waDateTime::getWeekdayNames();
        $week = ifset($schedule, 'week', []);

        foreach ($weekdays as $id => $day) {
            if ($week) {
                $start_work = ifset($week, $id, 'start_work', $start_time);
                $start_work = $prepare_time($start_work, $start_time);

                $end_work = ifset($week, $id, 'end_work', $end_time);
                $end_work = $prepare_time($end_work, $end_time);

                $end_processing = ifset($week, $id, 'end_processing', $end_processing_time);
                $end_processing = $prepare_time($end_processing, $end_processing_time);

                $weekdays[$id] = array(
                    'name'           => $day,
                    'work'           => (bool)ifset($week, $id, 'work', false),
                    'start_work'     => $start_work,
                    'end_work'       => $end_work,
                    'end_processing' => $end_processing,
                );

            // Old shop setting
            } elseif ($workhours) {
                $start_work = ifset($workhours, 'from', $start_time);
                $start_work = $prepare_time($start_work, $start_time);

                $end_work = ifset($workhours, 'to', $end_time);
                $end_work = $prepare_time($end_work, $end_time);

                $weekdays[$id] = array(
                    'name'           => $day,
                    'work'           => in_array($id, ifset($workhours, 'days', array())),
                    'start_work'     => $start_work,
                    'end_work'       => $end_work,
                    'end_processing' => $end_processing_time,
                );

            // Default
            } else {
                $weekdays[$id] = array(
                    'name'           => $day,
                    'work'           => ($id > 5) ? false : true,
                    'start_work'     => $start_time,
                    'end_work'       => $end_time,
                    'end_processing' => $end_processing_time,
                );
            }
        }

        // Work days
        $workdays = ifset($schedule, 'extra_workdays', []);
        $used_days = [];
        $date_validator = new waDateValidator();
        foreach ($workdays as $id => $day) {
            $date = ifset($day, 'date', null);
            if (!$date || !$date_validator->isValid($date)) {
                unset($workdays[$id]);
                continue;
            }

            $date = date('Y-m-d', strtotime($date));

            if (!$date || in_array($date, $used_days)) {
                unset($workdays[$id]);
                continue;
            }

            $used_days[] = $date;

            $start_work = ifset($day, 'start_work', $start_time);
            $start_work = $prepare_time($start_work, $start_time);

            $end_work = ifset($day, 'end_work', $end_time);
            $end_work = $prepare_time($end_work, $end_time);

            $end_processing = ifset($day, 'end_processing', $end_processing_time);
            $end_processing = $prepare_time($end_processing, $end_processing_time);

            $workdays[$id]['date'] = $date;
            $workdays[$id]['start_work'] = $start_work;
            $workdays[$id]['end_work'] = $end_work;
            $workdays[$id]['end_processing'] = $end_processing;
        }

        // Weekends
        $weekends = ifset($schedule, 'extra_weekends', []);
        $date_validator = new waDateValidator();
        foreach ($weekends as $id => $date) {
            if (empty($date) || !$date_validator->isValid($date)) {
                unset($weekends[$id]);
                continue;
            }

            $date = date('Y-m-d', strtotime($date));

            if (!$date || in_array($date, $used_days)) {
                unset($weekends[$id]);
                continue;
            }

            $weekends[$id] = $date;
        }

        $schedule['timezone'] = ifset($schedule, 'timezone', wa()->getUser()->getTimezone());
        $schedule['processing_time'] = ifset($schedule, 'processing_time', 0);
        $schedule['week'] = $weekdays;
        $schedule['extra_workdays'] = $workdays;
        $schedule['extra_weekends'] = array_unique($weekends);

        return $schedule;
    }

    public function explainLogs($logs)
    {
        $logs = parent::explainLogs($logs);
        $product_ids = array();
        foreach ($logs as $l_id => $l) {
            if (in_array($l['action'], array('product_add', 'product_edit')) && $l['params']) {
                $product_ids[] = $l['params'];
            }
        }
        if ($product_ids) {
            $product_model = new shopProductModel();
            $products = $product_model->getById($product_ids);
        }
        $app_url = wa()->getConfig()->getBackendUrl(true).$l['app_id'].'/';
        foreach ($logs as $l_id => $l) {
            if (in_array($l['action'], array('product_add', 'product_edit'))) {
                if (isset($products[$l['params']])) {
                    $p = $products[$l['params']];
                    $url = $app_url.'?action=products#/product/'.$l['params'].'/';
                    $logs[$l_id]['params_html'] = '<div class="activity-target"><a href="'.$url.'">'.htmlspecialchars($p['name']).'</a></div>';
                    if ($l['action'] == 'product_add' && !empty($p['image_id'])) {
                        $_is_2x_enabled = wa('shop')->getConfig()->getOption('enable_2x');
                        $_image_size = '96x96';
                        if ($_is_2x_enabled) {
                            $_image_size = '96x96@2x';
                        }
                        $img = shopImage::getUrl(array(
                            'id'         => $p['image_id'],
                            'product_id' => $p['id'],
                            'filename'   => $p['image_filename'],
                            'ext'        => $p['ext']
                        ), $_image_size);
                        $logs[$l_id]['params_html'] .= '<div class="activity-photo-wrapper">
                            <div class="activity-photo-list"><div class="photo-item"><a href="'.$url.'">
                            <img src="'.$img.'"></a></div></div></div>';
                    }
                }
            } elseif (substr($l['action'], 0, 6) == 'order_') {
                $url = $app_url.'#/order/'.$l['params'].'/';
                $logs[$l_id]['params_html'] = '<div class="activity-target"><a href="'.$url.'">'.
                    shopHelper::encodeOrderId($l['params']).'</a></div>';
            } elseif (in_array($l['action'], array('page_add', 'page_edit', 'page_move'))) {
                if (!empty($l['params_html'])) {
                    $logs[$l_id]['params_html'] = str_replace('#/pages/', '?action=storefronts#/pages/', $l['params_html']);
                }
            }
        }
        return $logs;
    }
}

function shop_currency($n, $in_currency = null, $out_currency = null, $format = true)
{
    if (is_array($in_currency)) {
        $options = $in_currency;
        $in_currency = ifset($options, 'in_currency', null);
        $out_currency = ifset($options, 'out_currency', null);
        if (array_key_exists('format', $options)) {
            $format = $options['format']; // can't use ifset because null is a valid value
        } else {
            $format = true;
        }
    }

    /**
     * @var shopConfig $config
     */
    $config = wa('shop')->getConfig();

    // primary currency
    $primary = $config->getCurrency(true);

    // current currency (in backend - it's primary, in frontend - currency of storefront)
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

    if (($format !== null) && ($info = waCurrency::getInfo($out_currency)) && isset($info['precision'])) {
        $n = round($n, $info['precision']);
    }

    if ($format === 'h') {
        return wa_currency_html($n, $out_currency);
    } elseif ($format) {
        if (empty($options['extended_format'])) {
            return wa_currency($n, $out_currency);
        } else {
            return waCurrency::format($options['extended_format'], $n, $currency);
        }
    } else {
        return str_replace(',', '.', $n);
    }

}

function shop_currency_html($n, $in_currency = null, $out_currency = null, $format = 'h')
{
    if (is_array($in_currency)) {
        $in_currency += array(
            'format' => $format,
        );
    }
    return shop_currency($n, $in_currency, $out_currency, $format);
}
