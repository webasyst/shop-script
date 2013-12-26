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

            'hash'     => '',
            'domain'   => '',
            'lifetime' => 0,
        );
        $current_domain = & $profile['config']['domain'];

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

        $type_model = new shopTypeModel();
        $this->view->assign('types', $type_model->getAll());
        $profile_map = ifset($profile['config']['map'], array());
        $export = ifset($profile['config']['export'], array());
        $set_model = new shopSetModel();
        $map = $this->plugin()->map(array(), null, true);
        if ($profile_map) {
            foreach ($map as $type => &$type_map) {
                foreach ($type_map['fields'] as $field => &$info) {
                    $info['source'] = ifempty($profile_map[$type][$field], 'skip:');
                    unset($info);
                }
                unset($type_map);
            }
        }
        $this->view->assign('sets', $set_model->getAll());
        $this->view->assign('type_map', $map);
        $this->view->assign('export', $export);


        $this->view->assign('types_map', ifset($profile['config']['types'], array()));

        $app_settings_model = new waAppSettingsModel();
        $this->view->assign('app_settings', array(
            'ignore_stock_count' => $app_settings_model->get('shop', 'ignore_stock_count', 0),
        ));


        $feature_model = new shopFeatureModel();
        if ($feature_model->countByField(array('parent_id' => null)) < 200) {
            $features = $feature_model->getFeatures(true);/*, true*/
            foreach ($features as $id => $feature) {
                if (!empty($feature['multiple'])
                    // || (strpos($feature['type'], shopFeatureModel::TYPE_RANGE.'.') === 0)
                    // || (strpos($feature['type'], shopFeatureModel::TYPE_2D.'.') === 0)
                    // || (strpos($feature['type'], shopFeatureModel::TYPE_3D.'.') === 0)
                    || ($feature['type'] == shopFeatureModel::TYPE_DIVIDER)
                ) {
                    unset($features[$id]);
                }
            }
            $this->view->assign('features', $features);
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

            if ($features = array_unique($features)) {
                $this->view->assign('features', $features = $feature_model->getFeatures('code', $features));
            } else {
                $this->view->assign('features', array());
            }
        }

        $fields = array(
            'name'        => _w('Product name'),
            'description' => _w('Description'),
            'summary'     => _w('Summary'),
            'count'       => _w('In stock'),
        );
        $this->view->assign('fields', $fields);
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
