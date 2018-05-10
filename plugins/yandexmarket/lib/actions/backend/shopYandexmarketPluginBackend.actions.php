<?php

class shopYandexmarketPluginBackendActions extends waViewActions
{
    private $plugin_id = 'yandexmarket';

    private function getProfile()
    {
        $profile_helper = new shopImportexportHelper($this->plugin_id);
        $this->view->assign('profiles', $list = $profile_helper->getList());

        $profile = $profile_helper->getConfig();

        $profile['config'] += array(
            'profile_id'   => $profile['id'],
            'hash'         => '',
            'domain'       => '',
            'lifetime'     => 0,
            'force_update' => 0,
        );
        return $profile;
    }

    public function setupAction()
    {
        $routing = wa()->getRouting();
        $settlements = array();

        $this->view->assign('custom_map', $this->plugin()->customMap());

        $profile = $this->getProfile();
        $current_domain = &$profile['config']['domain'];

        $host = preg_replace('@^www\.@', '', waRequest::server('http_host'));
        $current_host = preg_replace('@(^www\.|/.*$)@', '', $current_domain);

        $this->view->assign('current_domain', $current_domain);
        $domain_routes = $routing->getByApp('shop');
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                $settlement = $domain.'/'.$route['url'];
                if (($settlement == $current_domain) || ($current_domain === '')) {
                    $current_domain = $settlement;
                    $routing->setRoute($route, $domain);
                    waRequest::setParam($route);
                }
                $settlements[] = $settlement;
            }
        }
        $this->view->assign('ssl', ifset($profile['config']['ssl'], $host == $current_host ? waRequest::isHttps() : false));
        $this->view->assign('settlements', $settlements);

        $this->shippingAction($profile);


        $this->view->assign('info', $this->getFeedInfo($profile, $routing));

        /**
         * @var shopConfig $config ;
         */
        $config = wa('shop')->getConfig();


        $this->view->assign('company', ifempty($profile['config']['company'], $config->getGeneralSettings('name')));
        $this->view->assign('company_name', ifempty($profile['config']['company_name'], $config->getGeneralSettings('name')));
        $this->view->assign('company_phone', ifempty($profile['config']['company_phone']));
        $this->view->assign('default_phone', $config->getGeneralSettings('phone'));

        $type_model = new shopTypeModel();
        $this->view->assign('types', $type_model->getAll());
        $profile_map = ifset($profile['config']['map'], array());
        $export = ifset($profile['config']['export'], array());

        $set_model = new shopSetModel();
        $map = $this->plugin()->map(array(), null, true);
        $params = array();
        if ($profile_map) {
            foreach ($map as $type => &$type_map) {
                foreach ($type_map['fields'] as $field => &$info) {
                    $source = ifempty($profile_map[$type][$field], 'skip:');
                    $info['options'] = shopYandexmarketPlugin::parseMapOptions($source);
                    $info['source'] = $source;
                    unset($profile_map[$type][$field]);
                    unset($info);
                }
                if (!empty($type_map['fields']['param.*'])) {
                    $params[$type] = -1;
                }
                unset($type_map);
            }
            foreach ($profile_map as $type => $fields) {
                foreach ($fields as $field => $source) {
                    $info_field = (strpos($field, 'param.') === 0) ? 'param.*' : $field;
                    if (isset($map[$type]['fields'][$info_field])) {
                        $info = $map[$type]['fields'][$info_field];
                        $info['options'] = shopYandexmarketPlugin::parseMapOptions($source);
                        $info['source'] = ifempty($source, 'skip:');

                        $map[$type]['fields'][$field] = $info;
                        $params[$type] = max(ifset($params[$type], -1), intval(preg_replace('@\D+@', '', $field)));
                        unset($info);
                    }
                }
            }
        }

        $this->view->assign('sets', $set_model->getAll());
        $this->view->assign('type_map', $map);
        $this->view->assign('params', array('params' => $params));
        $this->view->assign('export', $export);
        $this->view->assign('trace', ifset($profile['config']['trace']));


        $this->view->assign('types_map', ifset($profile['config']['types'], array()));

        $app_settings_model = new waAppSettingsModel();
        $app_settings = array(
            'ignore_stock_count' => $app_settings_model->get('shop', 'ignore_stock_count', 0),
        );
        $this->view->assign('app_settings', $app_settings);
        $cron_template = 'php %s/cli.php shop yandexmarketPluginExport %d';
        $this->view->assign('cron_command', sprintf($cron_template, wa()->getConfig()->getRootPath(), $profile['id']));


        $this->assignMapFeatures($map);

        $fields = array(
            'name'        => _w('Product name'),
            'description' => _w('Description'),
            'summary'     => _w('Summary'),
            'sku'         => _w('SKU code'),
            'file_name'   => _w('Attachment'),
            'count'       => _w('In stock'),
            'type_id'     => _w('Product type'),
            'tax_id'     => _w('Tax rates'),
        );

        $stock_model = new shopStockModel();
        foreach ($stock_model->getAll() as $stock) {
            $fields[sprintf('stock_counts.%d', $stock['id'])] = sprintf('%s @ %s', _w('In stock'), $stock['name']);
        }

        if (class_exists('shopVirtualstockModel')) {
            $virtual_stock_model = new shopVirtualstockModel();
            $virtual_stocks = $virtual_stock_model->getAll('id');

            foreach ($virtual_stocks as $stock) {
                $fields[sprintf('virtual_stock_counts.%d', $stock['id'])] = sprintf('%s @@ %s', _w('In stock'), $stock['name']);
            }
        }

        $this->view->assign('fields', $fields);
    }

    public function shippingAction(&$profile = null)
    {
        if ($profile === null) {
            $profile = $this->getProfile();

            $profile['config']['home_region_id'] = waRequest::get('region_id', 0, waRequest::TYPE_INT);
        }

        $api_available = $this->plugin()->checkApi();
        $this->view->assign('api_available', $api_available);

        $address = array();
        if (!empty($profile['id']) && $api_available) {
            try {
                $address = $this->plugin()->getAddress($profile['config']);
                $this->view->assign('address', $address);
            } catch (waException $ex) {
                $this->view->assign('address_error', $ex->getMessage());
            }
        }

        /**
         * @var shopConfig $config ;
         */
        $config = wa('shop')->getConfig();

        $primary_currency = $config->getCurrency();

        if ($address) {
            $items = array(
                array(
                    'weight'   => 1.0,//base unit - kg
                    'price'    => 1,
                    'quantity' => 1,
                ),
            );

            $primary_currency = $this->plugin()->getSettings('primary_currency');

            switch ($primary_currency) {
                case 'auto':
                    $primary_currency = $config->getCurrency();
                    break;
                case 'front':
                    if (waRequest::param('currency')) {
                        $primary_currency = waRequest::param('currency');
                    } else {
                        $primary_currency = $config->getCurrency();
                    }
                    break;
            }

            $shipping_params = array(
                'no_external' => true,
                'currency'    => $primary_currency,
            );

            $shipping_methods = shopHelper::getShippingMethods($address['data'], $items, $shipping_params);
            $shipping_methods = array_filter($shipping_methods, create_function('$m', 'return empty($m["external"]);'));
            $this->view->assign('shipping_methods', $shipping_methods);
        }
        $this->view->assign('profile', $profile);
        $this->view->assign('primary_currency', $primary_currency);
    }

    public function regionAction()
    {
        try {
            $region_id = waRequest::request('region_id', 0, waRequest::TYPE_INT);
            $region = $this->plugin()->getRegions($region_id);
            asort($region['children']);
            $this->view->assign('region', $region);
        } catch (waException $ex) {
            $this->view->assign('error', $ex->getMessage());
        }
    }

    protected function getTemplate()
    {
        $module = 'backend';
        $template = parent::getTemplate();
        $search = sprintf('/%s/%s', $this->plugin_id, ucfirst($this->plugin_id));
        $replace = sprintf('/%s/%s', $module, ucfirst($module));
        $template = str_replace($search, $replace, $template);
        return $template;
    }

    /**
     *
     * @return shopYandexmarketPlugin
     */
    private function plugin()
    {
        static $plugin;
        if (!$plugin) {
            $plugin = wa()->getPlugin($this->plugin_id);
            /**
             * @var shopYandexmarketPlugin $plugin
             */
        }
        return $plugin;
    }

    /**
     * @param array $profile
     * @param waRouting $routing
     * @return array
     */
    private function getFeedInfo($profile, $routing = null)
    {
        $info = array();

        if (!empty($profile['id'])) {
            $path = shopYandexmarketPlugin::path($profile['id'].'.xml');
            $info['exists'] = file_exists($path);
            $info['mtime'] = $info['exists'] ? filemtime($path) : null;
        } else {
            $info['mtime'] = $info['exists'] = null;
        }

        if ($info['exists']) {
            $route_params = array(
                'plugin' => $this->plugin_id,
                'module' => 'frontend',
                'action' => 'catalog',
                'hash'   => $this->plugin()->getHash($profile['id']),
            );
            if (!$routing) {
                $routing = wa()->getRouting();
            }
            $info['url'] = $routing->getUrl('shop', $route_params, true);
        } else {
            $info['url'] = null;
        }
        return $info;
    }

    private function assignMapFeatures($map)
    {
        $feature_model = new shopFeatureModel();

        $limit = $this->getConfig()->getOption('features_per_page');
        if ($feature_model->countByField(array('parent_id' => null)) < $limit) {
            $features = $feature_model->getFeatures(true); /*, true*/
            foreach ($features as $id => $feature) {
                if ($feature['type'] == shopFeatureModel::TYPE_DIVIDER) {
                    unset($features[$id]);
                }
            }
        } else {
            $this->view->assign('features_autocomplete', true);

            $features = array();
            foreach ($map as $type_map) {
                foreach ($type_map['fields'] as $info) {
                    if (!empty($info['source']) && preg_match('@^feature:([\w\d_\-]+)(:.*)?$@', $info['source'], $matches)) {
                        $features[] = $matches[1];
                    }
                }
            }

            if ($features) {
                $features = $feature_model->getFeatures('code', array_unique($features));
            } else {
                $features = array();
            }
        }

        foreach ($features as $id => &$feature) {

            if (strpos($feature['type'], shopFeatureModel::TYPE_DIMENSION.'.') === 0) {
                $units = shopDimension::getUnits($feature['type']);

                $feature['units'] = array();
                foreach ($units as $unit) {
                    $feature['units'][] = $unit['title'];
                }
                $feature['units'] = implode(', ', $feature['units']);
            } elseif (preg_match('@\(([^\)]+)\)\s*$u@', $feature['name'], $matches)) {
                $feature['suggest'] = trim($matches[1]);
            }
            unset($feature);
        }
        $this->view->assign('features', $features);
        return $features;
    }
}
