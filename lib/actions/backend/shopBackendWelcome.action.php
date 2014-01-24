<?php
class shopBackendWelcomeAction extends waViewAction
{
    private $config;
    private $countries = array();
    private $types = array();
    private $translate = array();

    public function execute()
    {
        $path = $this->getConfig()->getConfigPath('data/welcome/', false);
        if (file_exists($path)) {
            $files = waFiles::listdir($path, false);
            foreach ($files as $file) {
                if (preg_match('/^country_([a-z]{3})\.php$/', $file, $matches)) {
                    $this->countries[$matches[1]] = $matches[1];
                } elseif (preg_match('/^type_([a-z]\w+)\.php$/', $file, $matches)) {
                    $this->types[$matches[1]] = include($path.$file);
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
            $feature_model = new shopFeatureModel();
            $type_features_model = new shopTypeFeaturesModel();

            $types = waRequest::post('types');
            if (empty($types)) {
                if (!$type_features_model->countAll()) {
                    $types[] = 'default';
                }
            }
            if ($types) {
                foreach ($types as $type) {
                    if (!empty($this->types[$type])) {
                        $type = $this->types[$type];
                        $type['name'] = ifempty($this->translate[$type['name']], $type['name']);
                        $type['id'] = $type_model->insert($type);
                        if ($type['id'] && !empty($type['features'])) {
                            foreach ($type['features'] as $code => $feature) {
                                $feature += array(
                                    'type'       => 'varchar',
                                    'selectable' => false,
                                    'multiple'   => false,
                                );
                                $feature['types'] = array($type['id']);
                                $feature['name'] = ifempty($this->translate[$feature['name']], $feature['name']);
                                $feature['code'] = $code;
                                $id = null;
                                if ($data = $feature_model->getByField('code', $code)) {
                                    if (($feature['type'] == $data['type']) && ($feature['selectable'] == $data['selectable']) && ($feature['multiple'] == $data['multiple'])) {
                                        $id = $data['id'];
                                    }

                                }
                                $feature['id'] = $feature_model->save($feature, $id);

                                if ($feature['id'] && !empty($feature['selectable']) && !empty($feature['values'])) {
                                    foreach ($feature['values'] as & $value) {
                                        if (is_string($value)) {
                                            $value = ifempty($this->translate[$value], $value);
                                        } elseif (isset($value['value'])) {
                                            $value['value'] = ifempty($this->translate[$value['value']], $value['value']);
                                        }
                                    }
                                    unset($value);
                                    $feature_model->setValues($feature, $feature['values'], false, true);
                                }
                                $type_features_model->updateByFeature($feature['id'], $feature['types'], false);
                            }
                        }
                    }
                }
            }
        }

        $set_model = new shopSetModel();
        $set_model->add(array(
            'id'   => 'promo',
            'name' => _w('Featured on homepage'),
            'type' => shopSetModel::TYPE_STATIC,
        ));
        $set_model->add(array(
            'id'    => 'bestsellers',
            'name'  => _w('Bestsellers'),
            'type'  => shopSetModel::TYPE_DYNAMIC,
            'count' => 8,
            'rule'  => 'rating DESC',
        ));


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

        /* !!! import commented out on welcome screen
         switch (waRequest::post('import')) {
         case 'demo':
         //TODO create demoproducts
         $this->redirect('?action=products');
         break;
         case 'migrate':
         $plugins = $this->getConfig()->getPlugins();
         if (empty($plugins['migrate'])) {
         $url = $this->getConfig()->getBackendUrl(true).'installer/?module=update&action=manager&install=1&app_id[shop/plugins/migrate]=webasyst';
         } else {
         $url = '?action=importexport#/migrate/';
         }
         $this->redirect($url);
         break;
         case 'scratch':
         default: */
        $this->redirect('?action=products#/welcome/');
        //        break;
        //}
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
    }
}
