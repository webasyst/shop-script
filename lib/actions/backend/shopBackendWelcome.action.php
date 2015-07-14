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
            $app_settings_model->set('shop', 'show_tutorial', 1);
            $app_settings_model->del('shop', 'welcome');
            $this->setup();
        } else {
            $this->overview();
        }
    }

    private function setup()
    {
        if ($country = waRequest::post('country')) {
            if (!empty($this->countries[$country])) {
                $path = $this->getConfig()->getConfigPath('data/welcome/', false);

                $country_data = include($path."country_{$country}.php");

                # Main country setting
                $model = new waAppSettingsModel();
                $model->set('shop', 'country', $country);

                #currency
                if (!empty($country_data['currency'])) {
                    $currency_model = new shopCurrencyModel();
                    $sort = 0;
                    foreach ($country_data['currency'] as $code => $rate) {

                        // delete old currency info is exists
                        $currency_model->deleteById($code);

                        $currency_model->insert(array('code' => $code, 'rate' => $rate, 'sort' => $sort++), 2);
                        if ($sort == 1) {
                            $model->set('shop', 'currency', $code);
                        }
                    }

                }

                #taxes
                if (!empty($country_data['taxes'])) {
                    foreach ($country_data['taxes'] as $tax_data) {
                        shopTaxes::save($tax_data);
                    }
                }

                // Customer filters (country depended)
                $customers_filter_model = new shopCustomersFilterModel();
                if (method_exists($customers_filter_model, 'addWelcomeCountry' . ucfirst($country) . 'Filters')) {
                    $method = 'addWelcomeCountry' . ucfirst($country) . 'Filters';
                    $customers_filter_model->$method();
                }

                #custom code
                $function = 'shopWelcome'.ucfirst($country);
                if (function_exists($function)) {
                    try {
                        call_user_func_array($function, array());
                    } catch (Exception $ex) {
                        //TODO
                        ;
                    }
                }
            }
        }

        if (!empty($this->types)) {
            $type_model = new shopTypeModel();
            $type_features_model = new shopTypeFeaturesModel();

            $types = waRequest::post('types');
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


// notifications
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
        if (empty($redirect)) {
            $redirect = '?module=tutorial#/products/';
        }

        // Promos
        $promo_model = new shopPromoModel();
        if ($promo_model->countAll() <= 0) {
            $promo_routes = array();
            $promo_routes_model = new shopPromoRoutesModel();
            $promo_stubs = include(wa()->getAppPath('lib/config/data/promos.php', 'shop'));
            foreach ($promo_stubs as $stub) {
                $file = $stub['image'];
                $ext = explode('.', $file);
                $ext = array_pop($ext);
                unset($stub['image']);
                $id = $promo_model->insert($stub + array(
                        'type' => 'link',
                        'ext'  => $ext,
                    ));
                waFiles::copy(wa()->getAppPath($file, 'shop'), wa('shop')->getDataPath('promos/'.$id.'.'.$ext, true));
                $promo_routes[] = array(
                    'promo_id'   => $id,
                    'storefront' => '%all%',
                    'sort'       => count($promo_routes) + 1,
                );
            }
            $promo_routes_model->multipleInsert($promo_routes);
        }

        // Customer filters (other, i.e. not country depended)
        $customers_filter_model = new shopCustomersFilterModel();
        $customers_filter_model->addWelcomeRefererFacebookFilter();
        $customers_filter_model->addWelcomeRefererTwitterFilter();
        $customers_filter_model->addWelcomeLastOrderedMonthAgoFilter();

        $this->redirect($redirect);
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

        $this->view->assign('countries', $countries);
        $this->view->assign('country_iso', $country_iso3);

        $this->view->assign('translate', $this->translate);

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
        $this->view->assign('types', $types);

        $backend_welcome = wa()->event('backend_welcome');


        $params = array(
            'title_wrapper'       => '%s',
            'description_wrapper' => '<br><span class="hint">%s</span>',
            'control_wrapper'     => '
<div class="field">
    <div class="name">%s</div>
    <div class="value no-shift">%s%s</div>
</div>
',
            'control_separator'   => '</div><div class="value">',
        );
        foreach ($backend_welcome as $plugin => &$data) {
            if (isset($data['controls'])) {
                if (is_array($data['controls'])) {
                    $controls = array();
                    foreach ($data['controls'] as $name => $row) {
                        if (is_array($row)) {
                            $row = array_merge($row, $params);
                            waHtmlControl::addNamespace($row, $plugin);
                            if (isset($options[$name])) {
                                $row['options'] = $options[$name];
                            }
                            if (isset($params['value']) && isset($params['value'][$name])) {
                                $row['value'] = $params['value'][$name];
                            }
                            if (!empty($row['control_type'])) {
                                $controls[$name] = waHtmlControl::getControl($row['control_type'], $name, $row);
                            }
                        } else {
                            $controls[$name] = $row;
                        }
                    }
                    $data['controls'] = implode("\n", $controls);
                }
            }
            unset($data);
        }
        $this->view->assign('backend_welcome', $backend_welcome);
    }
}
