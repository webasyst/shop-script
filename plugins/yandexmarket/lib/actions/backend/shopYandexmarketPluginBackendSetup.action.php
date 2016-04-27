<?php

class shopYandexmarketPluginBackendSetupAction extends waViewAction
{
    private $plugin_id = 'yandexmarket';

    public function execute()
    {
        $routing = wa()->getRouting();
        $settlements = array();

        $profile_helper = new shopImportexportHelper($this->plugin_id);
        $this->view->assign('profiles', $list = $profile_helper->getList());
        $profile = $profile_helper->getConfig();

        $profile['config'] += array(
            'hash'         => '',
            'domain'       => '',
            'lifetime'     => 0,
            'force_update' => 0,
        );
        $current_domain = &$profile['config']['domain'];

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

        $this->view->assign('profile', $profile);
        $info = array();

        $this->view->assign('settlements', $settlements);
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
                'hash'   => $this->plugin()->getHash($profile['id']),
            );
            $info['url'] = $routing->getUrl('shop/frontend/catalog', $route_params, true);
        } else {
            $info['url'] = null;
        }
        $this->view->assign('info', $info);
        /**
         * @var shopConfig $config ;
         */
        $config = wa('shop')->getConfig();
        $this->view->assign('primary_currency', $config->getCurrency());

        $this->view->assign('company', ifempty($profile['config']['company'], $config->getGeneralSettings('name')));
        $this->view->assign('company_name', ifempty($profile['config']['company_name'], $config->getGeneralSettings('name')));

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
                    $info['source'] = ifempty($profile_map[$type][$field], 'skip:');
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
                        $info['source'] = ifempty($source, 'skip:');

                        $map[$type]['fields'][$field] = $info;
                        $params[$type] = max(ifset($params[$type], -1), intval(preg_replace('@\D+@', '', $field)));
                    }
                }
            }
        }

        $this->view->assign('sets', $set_model->getAll());
        $this->view->assign('type_map', $map);
        $this->view->assign('params', array('params' => $params));
        $this->view->assign('export', $export);

        $this->view->assign('shipping_methods', shopHelper::getShippingMethods(array()));


        $this->view->assign('types_map', ifset($profile['config']['types'], array()));

        $app_settings_model = new waAppSettingsModel();
        $app_settings = array(
            'ignore_stock_count' => $app_settings_model->get('shop', 'ignore_stock_count', 0),
        );
        $this->view->assign('app_settings', $app_settings);


        $feature_model = new shopFeatureModel();
        $config = wa('shop')->getConfig();
        /**
         * @var shopConfig $config
         */
        $limit = $config->getOption('features_per_page');
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
                    if (!empty($info['source']) && preg_match('@^feature:([\w\d_\-]+)$@', $info['source'], $matches)) {
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
            } elseif (preg_match('@\(([^\)]+)\)$@', $feature['name'], $matches)) {
                $feature['units'] = trim($matches[1]);
            }
            unset($feature);
        }

        $this->view->assign('features', $features);

        $fields = array(
            'name'        => _w('Product name'),
            'description' => _w('Description'),
            'summary'     => _w('Summary'),
            'sku'         => _w('SKU code'),
            'file_name'   => _w('Attachment'),
            'count'       => _w('In stock'),
        );
        $stock_model = new shopStockModel();
        foreach ($stock_model->getAll() as $stock) {
            $fields[sprintf('todo:stock:%d', $stock['id'])] = sprintf('%s @ %s', _w('In stock'), $stock['name']);
        }

        $this->view->assign('fields', $fields);

        $this->view->assign('shipping', shopHelper::getShippingMethods());
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
        }
        return $plugin;
    }
}
