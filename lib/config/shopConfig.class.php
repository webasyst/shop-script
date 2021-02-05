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

    public function checkUpdates()
    {
        parent::checkUpdates();
        $this->installAfter();
    }

    protected function installAfter()
    {
        $model = new waAppSettingsModel();
        $create_locale_configs = $model->get('shop', 'create_locale_configs', 0);
        if ($create_locale_configs && wa()->getUser()->isAuth()) {
            $old_active = waSystem::getApp();
            if ($old_active != 'shop') {
                waSystem::setActive('shop');
            }
            include($this->getAppPath('lib/config/install.after.php'));
            $model->del('shop', 'create_locale_configs');
            waSystem::setActive($old_active);
        }
    }

    public function checkRights($module, $action)
    {
        static $event_done = false;
        if (!$event_done) {
            $event_done = true;

            if (wa()->getEnv() === 'backend') {
                /**
                 * Modify current backend user rights.
                 * E.g. plugin can return [ 'orders' => 1 ] to grant current user access to Orders tab.
                 *
                 * @event backend_rights
                 * @param string $module
                 * @param string $action
                 * @return array[string]array $return[%plugin_id%] rights to set for current user; array of key => value pairs
                 */
                $result = wa()->event(array($this->application, 'backend_rights'), ref([
                    'module' => $module,
                    'action' => $action,
                ]));
                if ($result) {
                    $contact_id = wa()->getUser()->getId();
                    $rights_model = new waContactRightsModel();
                    foreach ($result as $rights) {
                        if ($rights && is_array($rights)) {
                            $rights_model->saveOnce($contact_id, $this->application, $rights);
                        }
                    }
                }
            }
        }

        if ($module == 'frontend' && waRequest::param('ssl') &&
            (strpos($action, 'my') === 0 || $action === 'checkout')) {
            if (!waRequest::isHttps()) {
                $url = 'https://'.waRequest::server('HTTP_HOST').wa()->getConfig()->getCurrentUrl();
                wa()->getResponse()->redirect($url, 301);
            }
        } elseif (substr($module, 0, 5) == 'order' || $module == 'coupons' || $module == 'workflow') {
            return wa()->getUser()->getRights('shop', 'orders');
        } elseif (substr($module, 0, 9) == 'marketing') {
            if ($action === 'CouponsAutocomplete') {
                return true;
            }
            return wa()->getUser()->getRights('shop', 'marketing');
        } elseif (substr($module, 0, 7) == 'reports') {
            return wa()->getUser()->getRights('shop', 'reports');
        } elseif (substr($module, 0, 8) == 'settings') {
            return wa()->getUser()->getRights('shop', 'settings');
        } elseif (substr($module, 0, 7) == 'service') {
            return wa()->getUser()->getRights('shop', 'services');
        } elseif (substr($module, 0, 9) == 'customers') {
            return wa()->getUser()->getRights('shop', 'customers');
        } elseif ($module == 'importexport' || $module == 'csv' || $module == 'images') {
            return wa()->getUser()->getRights('shop', 'importexport');
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
        if (empty($route['is_backend_route'])) {
            $url_type = isset($route['url_type']) ? $route['url_type'] : 0;
        } else {
            $url_type = 'backend';
        }
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
                        // apps cannot add routes to other apps
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

    protected function getRoutingRules($route = array())
    {
        $path = $this->getRoutingPath('frontend');
        if (file_exists($path)) {
            $routes = include($path);
        } else {
            $routes = array();
        }

        if ($this->getEnvironment() === 'backend') {
            $path = $this->getRoutingPath('backend');
            if (file_exists($path)) {
                $routes['backend'] = include($path);
            }
        }

        return $routes;
    }

    protected function getRoutingPath($type)
    {
        if ($type === null) {
            $type = $this->getEnvironment();
        }
        $filename = ($type === 'backend') ? 'routing.backend.php' : 'routing.php';
        $path = $this->getConfigPath($filename, true, $this->application);
        if (!file_exists($path)) {
            $path = $this->getConfigPath($filename, false, $this->application);
        }
        return $path;
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
                         'allow_image_upload'            => 0,
                         'moderation_reviews'            => 0,
                         'review_service_agreement'      => '',
                         'review_service_agreement_hint' => '',
                         'sort_order_items'              => 'user_cart',
                         'merge_carts'                   => 0,
                     ) as $k => $value) {
                $settings[$k] = isset($all_settings[$k]) ? $all_settings[$k] : $value;
            }

            $settings['workhours'] = $this->prepareWorkHours();
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

    /**
     * This method is required for backward compatibility.
     * Prior to version 8.0, the shop work schedule was stored in a different format.
     * This method will try to collect the new schedule in the old format.
     * Someday we will try to get rid of it. But not today.
     * If the developer of design theme reads this, we advise him to use the smarty-helper $wa->shop->schedule()
     * Setting $wa->shop->settings('workhours') is deprecated.
     * @return array
     * @throws waException
     * @deprecated
     *
     */
    protected function prepareWorkHours()
    {
        $workhours = array(
            'hours_from'   => null,
            'hours_to'     => null,
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

        $schedule = $this->getStorefrontSchedule();

        $schedule['week'][0] = $schedule['week'][7];
        unset($schedule['week'][7]);

        foreach ($schedule['week'] as $day_id => $day) {
            // Get all work days
            if ($day['work']) {
                $workhours['days'][$day_id] = $strings[$day_id];
            }
            // Set the opening hours of the first working day
            if (!$workhours['hours_from'] && $day['work']) {
                $workhours['hours_from'] = $day['start_work'];
                $workhours['hours_to'] = $day['end_work'];
            }
        }

        $workhours['days_from_to'] = self::getDaysFromTo(array_keys($workhours['days']), $strings);

        return $workhours;
    }

    /**
     * Helper for getGeneralSettings(). Builds a human-readable string describing work days of a shop.
     * @param int[] $work_days                - array of work days, where day is day number of week (0..6, where 0 is Sunday and 6 is Saturday)
     * @param array<int, string> $day_names   - name of week days, where key is day number of week (0..6) and value is week day name
     * @return string
     * @throws waException
     */
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
        $i = $first_day_of_week;    // $i is day number of week

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
        return max(min($width, 1000), 200);
    }

    public function setSidebarWidth($width)
    {
        $width = max(min((int)$width, 1000), 200);
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
        if (!$plugin_model->listPlugins(shopCheckout::STEP_SHIPPING) && isset($steps['shipping'])) {
            unset($steps[shopCheckout::STEP_SHIPPING]);
        }
        if (!$plugin_model->listPlugins(shopCheckout::STEP_PAYMENT) && isset($steps['payment'])) {
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
            $schedule = json_decode(wa()->getSetting('schedule', '{}', 'shop'), true);
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
            if (!$date || !$date_validator->isValid($date, false)) {
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
        foreach ($weekends as $id => $date) {
            if (empty($date) || !$date_validator->isValid($date, false)) {
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

    public function getStorefrontSchedule()
    {
        $route = wa()->getRouting()->getRoute();
        $app = ifset($route, 'app', null);
        if ($app !== 'shop') {
            $route = $this->getStorefrontRoute();
        }

        $checkout_version = (int)ifset($route, 'checkout_version', 1);
        $storefront_id = ifset($route, 'checkout_storefront_id', null);

        if ($checkout_version == 2 && $storefront_id) {
            $checkout_config = new shopCheckoutConfig($storefront_id);
            return $checkout_config['schedule'];
        }

        return $this->getSchedule();
    }

    /**
     * Returns the rule from routing to storefront
     * @return null|array
     */
    public function getStorefrontRoute()
    {
        $storefronts = shopHelper::getStorefronts(true);
        $shop_url = preg_replace('~^https?://|[\\\\/]$~i', '', wa()->getRouteUrl('shop/frontend', [], true));
        foreach ($storefronts as $storefront) {
            if (isset($storefront['route']) && isset($storefront['url'])) {
                $storefront_url = rtrim($storefront['url'], '\/');
                if ($storefront_url == $shop_url) {
                    return $storefront['route'];
                }
            }
        }

        return null;
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

    /**
     * Get identity hash (aka installation hash)
     * @return string
     */
    public function getIdentityHash()
    {
        $value = $this->getSystemOption('identity_hash');
        if (is_scalar($value)) {
            return strval($value);
        }
        return '';
    }

    /**
     * @param string $method
     *  - 'feedback' - send feedback about something (for example about new shop editor)
     * @return string
     */
    public function getWebasystApiUrl($method)
    {
        $api_host = $this->getOption('webasyst_api_host');
        if (!$api_host) {
            $api_host = 'https://www.webasyst.com';
        }
        $api_host = trim($api_host, '/') . '/';

        $url = '';
        switch ($method) {
            case 'feedback':
                $url = $api_host . 'my/ajax/feedback/';
                break;
            default:
                break;
        }

        return $url;
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
            $n = floatval($n) * $currencies[$in_currency]['rate'];
        }
        if ($out_currency != $primary) {
            $n = floatval($n) / ifempty($currencies, $out_currency, 'rate', 1.0);
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
