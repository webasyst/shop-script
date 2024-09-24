<?php

class shopBackendWelcomeAction extends waViewAction
{
    private $config;
    private $countries = array();
    private $types = array();
    private $translate = array();

    public function execute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waException(_w('Access denied'));
        }
        $path = $this->getConfig()->getConfigPath('data/welcome/', false);
        if (file_exists($path)) {
            $files = waFiles::listdir($path, false);
            $this->types = shopTypeModel::getTemplates();
            foreach ($files as $file) {
                if (preg_match('/^country_([a-z]{3})\.php$/', $file, $matches)) {
                    $this->countries[$matches[1]] = $matches[1];
                }
            }
        }
        $locale_path = $path.'locale/'.$this->getUser()->getLocale().'.php';
        if (file_exists($locale_path)) {
            $this->translate = include($locale_path);
            if (!is_array($this->translate)) {
                $this->translate = array();
            }
        }

        if (waRequest::post()) {
            $app_settings_model = new waAppSettingsModel();

            $passed_steps = json_decode($app_settings_model->get('shop', 'tutorial_passed_steps', '[]'), true);
            $passed_action = waRequest::post('passed_action', null, waRequest::TYPE_STRING);
            if ($passed_action && !in_array($passed_action,$passed_steps)) {
                $passed_steps[] = $passed_action;
                $app_settings_model->set('shop', 'tutorial_passed_steps', json_encode($passed_steps));
            }

            $welcome = $app_settings_model->get('shop', 'welcome', false);
            $app_settings_model->set('shop', 'show_tutorial', 1);
            if ($welcome) {
                $app_settings_model->del('shop', 'welcome');
                $this->setup();
            } else {
                //If Welcome is passed, then just go to the next page
                $this->redirectToProducts();
            }

        } else {
            $this->overview();
        }
    }

    private function setup()
    {
        $setup_options = [];

        $country = waRequest::post('country');
        if ($country && !empty($this->countries[$country])) {
            $setup_options['country'] = $country;
        }

        $currency = waRequest::post('currency');
        if ($currency) {
            $setup_options['currency'] = $currency;
        }

        $demo_db = waRequest::post('demo_db', null, 'int');
        if (wa_is_int($demo_db)) {
            $setup_options['demo_db'] = $demo_db;
        }

        self::setupEverything($setup_options);

        $redirect = null;
        if ($plugin_id = waRequest::post('plugin')) {
            $data = waRequest::post($plugin_id);
            if (preg_match('@^(.+)-plugin$@', $plugin_id, $matches)) {
                $plugin_id = $matches[1];
                if (($plugin = wa('shop')->getPlugin($plugin_id)) && (method_exists($plugin, 'getWelcomeUrl'))) {
                    $redirect = $plugin->getWelcomeUrl($data);
                }
            }
        }

        $this->redirectToProducts($redirect);
    }

    public static function setupEverything($options)
    {
        $country = ifset($options, 'country', 'rus');
        $currency = ifset($options, 'currency', 'rus');
        $demo_db = ifset($options, 'demo_db', 'rus');

        self::setupCountry($country);
        self::setupTaxes($country);
        self::setupCustomerFilters($country);

        self::setupCurrency($currency);

        self::setupDemo($demo_db);
        self::setupTypes();

        self::setupBasicSets();
        self::setupBasicNotifications();
        self::setupBasicPromos();

        // Customer filters (other, i.e. not country depended)
        $customers_filter_model = new shopCustomersFilterModel();
        if (wa()->getLocale() !== 'ru_RU') {
            $customers_filter_model->addWelcomeRefererFacebookFilter();
            $customers_filter_model->addWelcomeRefererTwitterFilter();
        }
        $customers_filter_model->addWelcomeLastOrderedMonthAgoFilter();

        // Clear cache. Cloud has problem with cache
        $app_settings_model = new waAppSettingsModel();
        $app_settings_model->clearCache('shop');
    }

    private function overview()
    {
        $this->setLayout(new shopWelcomeLayout());

        #countries
        $cm = new waCountryModel();
        $cm->preload();
        $countries = array();
        if (!empty($this->countries)) {
            foreach ($this->countries as $iso3) {
                $countries[$iso3] = $cm->get($iso3);
            }
        }
        $locale = waLocale::getInfo(wa()->getUser()->getLocale());
        if (!isset($locale['iso3']) || !isset($countries[$locale['iso3']])) {
            if (isset($countries['usa'])) {
                $country_iso3 = 'usa';
            } else {
                reset($countries);
                $country_iso3 = key($countries);
            }
        } else {
            $country_iso3 = $locale['iso3'];
        }

        $app_settings_model = new waAppSettingsModel();
        $tutorial_visible = $app_settings_model->get('shop', 'show_tutorial') || waRequest::request('module') == 'tutorial';

        $currencies = $this->getCurrencies();
        $locale_currency = ifset($locale, 'currency', null);
        if (wa()->whichUI() == '2.0') {
            // backend_welcome event existed before 8.17.1, removed in 8.18.0; added back with different parameters in 11.0.0
            $backend_welcome_event_result = wa('shop')->event('backend_welcome', ref([
                'countries'         => &$countries,
                'country_iso'       => &$country_iso3,
                'locale_currency'   => &$locale_currency,
                'currencies'        => &$currencies,
            ]));
        }

        $this->view->assign(array(
            'countries'         => $countries,
            'country_iso'       => $country_iso3,
            'locale_currency'   => $locale_currency,
            'translate'         => $this->translate,
            'actions'           => shopTutorialActions::getActions(true),
            'currencies'        => $currencies,
            //'types'             => $this->getShortedProductTypes(),
            'tutorial_progress' => shopTutorialActions::getTutorialProgress(),
            'tutorial_visible'  => $tutorial_visible,
            'backend_welcome_event_result' => $backend_welcome_event_result,
            'shop_demo_data_source_list' => shopDemoDataImporter::getSourceList(),
            'shop_demo_importer_can_work' => shopDemoDataImporter::canWork()
        ));
    }

    public function redirectToProducts($redirect = null)
    {
        if (!$redirect) {
            $redirect = '?action=products';
            if (wa()->whichUI() == '1.3') {
                $redirect = '?module=tutorial#/products/';
            }
        }

        $this->redirect($redirect);
    }

    protected static function setupBasicPromos()
    {
        $promo_model = new shopPromoModel();
        $promo_rules_model = new shopPromoRulesModel();
        $promo_routes_model = new shopPromoRoutesModel();

        if ($promo_model->countAll() <= 0) {
            $promo_routes = [];
            $promo_stubs_path = wa()->getAppPath('lib/config/data/promos.php', 'shop');

            if (file_exists($promo_stubs_path)) {
                $promo_stubs = include($promo_stubs_path);

                foreach ($promo_stubs as $stub) {
                    $promo = $stub;
                    $promo_rules = ifempty($promo, 'rules', []);
                    unset($promo['rules']);

                    // Create promo
                    $promo_id = $promo_model->insert($promo);

                    // Create promo rules
                    if (!empty($promo_rules)) {
                        foreach ($promo_rules as &$promo_rule) {
                            $promo_rule['promo_id'] = $promo_id;

                            // Prepare banner tools
                            if ($promo_rule['rule_type'] == 'banner') {
                                foreach ($promo_rule['rule_params']['banners'] as &$banner) {
                                    $banner['image_filename'] = shopPromoBannerHelper::generateImageName().'.'.pathinfo($banner['image'], PATHINFO_EXTENSION);
                                    $banner_path = shopPromoBannerHelper::getPromoBannerPath($promo_id, $banner['image_filename']);
                                    waFiles::copy(wa()->getAppPath($banner['image'], 'shop'), $banner_path);
                                    unset($banner['image']);
                                }
                                unset($banner);
                            }

                            if (is_array($promo_rule['rule_params'])) {
                                $promo_rule['rule_params'] = waUtils::jsonEncode($promo_rule['rule_params']);
                            }
                        }
                        unset($promo_rule);
                        $promo_rules_model->multipleInsert($promo_rules);
                    }

                    // Prepare promo routes
                    $promo_routes[] = [
                        'promo_id'   => $promo_id,
                        'storefront' => shopPromoRoutesModel::FLAG_ALL,
                        'sort'       => count($promo_routes) + 1,
                    ];
                }

                // Crate promo routes
                $promo_routes_model->multipleInsert($promo_routes);
            }

        }

        return true;
    }

    protected static function setupBasicNotifications()
    {
        $notifications_model = new shopNotificationModel();
        if ($notifications_model->countAll() == 0) {
            $notifications_action = new shopSettingsNotificationsAddAction();
            $notifications = $notifications_action->getTemplates();
            $params_model = new shopNotificationParamsModel();
            $events = $notifications_action->getEvents();
            foreach ($notifications as $event => $n) {
                if ($event == 'order') {
                    continue;
                }
                $data = array(
                    'name'      => $events[$event]['name'].' ('._w('Customer').')',
                    'event'     => $event,
                    'transport' => 'email',
                    'status'    => 1,
                );
                $id = $notifications_model->insert($data);
                $params = $n;
                $params['to'] = 'customer';
                $params_model->save($id, $params);

                if ($event == 'order.create') {
                    $data['name'] = $events[$event]['name'].' ('._w('Store admin').')';
                    $id = $notifications_model->insert($data);
                    $params['to'] = 'admin';
                    $params_model->save($id, $params);
                }
            }
        }

        return true;
    }

    protected static function setupBasicSets()
    {
        $set_model = new shopSetModel();
        $set_model->add(
            array(
                'id'   => 'promo',
                'name' => _w('Featured on homepage'),
                'type' => shopSetModel::TYPE_STATIC,
            )
        );
        $set_model->add(
            array(
                'id'    => 'bestsellers',
                'name'  => _w('Bestsellers'),
                'type'  => shopSetModel::TYPE_DYNAMIC,
                'count' => 8,
                'rule'  => 'rating DESC',
            )
        );
        return true;
    }

    /**
     * @return bool
     */
    protected static function setupDemo($demo_db)
    {
        if (!wa_is_int($demo_db)) {
            return false;
        }

        $source = new shopDemoDataImporter($demo_db);
        $result = $source->import();

        if ($result) {
            $asm = new waAppSettingsModel();
            $asm->set('shop', 'setup_demo_time', time());
        }

        return $result;
    }

    protected static function setupTypes()
    {
        //$types = waRequest::post('types');
        $types = array();

        $type_model = new shopTypeModel();
        if ($type_model->countAll() <= 0) {
            $type_features_model = new shopTypeFeaturesModel();

            if (empty($types)) {
                if (!$type_features_model->countAll()) {
                    $types[] = 'default';
                }
            }
            if ($types) {
                foreach ($types as $type) {
                    $type_model->insertTemplate($type);
                }
            }
        }

        return true;
    }

    protected static function setupCountry($country)
    {
        # Main country setting
        $model = new waAppSettingsModel();
        $model->set('shop', 'country', $country);
        return true;
    }

    protected static function setupCurrency($currency)
    {
        $all_currencies = waCurrency::getAll(true);

        if (isset($all_currencies[$currency])) {
            $currency_model = new shopCurrencyModel();
            wa('shop')->getConfig()->setCurrency($currency);
            $currency_model->add($currency);
            $currency_model->setPrimaryCurrency($currency);
        } else {
            throw new waException('Currency not found');
        }

        return true;
    }

    protected static function setupTaxes($country)
    {
        $path = wa('shop')->getConfig()->getConfigPath('data/welcome/', false);
        $country_config_path = $path."country_{$country}.php";

        if (file_exists($country_config_path)) {
            $country_data = include($country_config_path);

            if (!empty($country_data['taxes'])) {
                $tax_model = new shopTaxModel();
                foreach ($country_data['taxes'] as $tax_data) {
                    if (!$tax_model->getByName($tax_data['name'])) {
                        shopTaxes::save($tax_data);
                    }
                }
            }
        }

        return true;
    }

    protected static function setupCustomerFilters($country)
    {
        // Customer filters (country depended)
        $customers_filter_model = new shopCustomersFilterModel();
        if (method_exists($customers_filter_model, 'addWelcomeCountry'.ucfirst($country).'Filters')) {
            $method = 'addWelcomeCountry'.ucfirst($country).'Filters';
            $customers_filter_model->$method();
        }

        return true;
    }

    /**
     * Get product types without features
     * @return array
     */
    protected function getShortedProductTypes()
    {
        #product types
        $types = array();
        if (!empty($this->types)) {
            foreach ($this->types as $id => $type) {
                if ($id != 'default') {
                    $name = ifempty($type['name'], $id);
                    $types[$id] = array(
                        'name'        => ifempty($this->translate[$name], $name),
                        'icon'        => ifempty($type['icon'], ''),
                        'description' => '',
                    );
                }
            }
        }

        return $types;
    }

    /**
     * Get all currencies
     * @return array|null
     */
    protected function getCurrencies()
    {
        $all_currencies = waCurrency::getAll(true);

        $currencies = array(
            array(
                'code'  => 'RUB',
                'title' => $all_currencies['RUB']['title'],
            ),
            array(
                'code'  => 'UAH',
                'title' => $all_currencies['UAH']['title'],
            ),
            array(
                'code'  => 'BYN',
                'title' => $all_currencies['BYN']['title'],
            ),
            array(
                'code'  => 'USD',
                'title' => $all_currencies['USD']['title'],
            ),
            array(
                'code'  => 'EUR',
                'title' => $all_currencies['EUR']['title'],
            ),
            array(
                'code'  => null,
                'title' => null,
            ),
        );

        uasort($all_currencies, wa_lambda('$a, $b', 'return strcmp($a["title"], $b["title"]);'));

        foreach ($all_currencies as $currency) {
            $currencies[] = array(
                'code'  => $currency['code'],
                'title' => $currency['title'],
            );
        }

        return $currencies;
    }
}
