<?php
class shopYandexmarketPluginBackendSetupAction extends waViewAction
{
    public function execute()
    {
        $routing = wa()->getRouting();
        $settlements = array();
        $this->view->assign('current_domain', $current_domain = $this->plugin()->getSettings('domain'));
        $domain_routes = $routing->getByApp('shop');
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                $settlement = $domain.'/'.$route['url'];
                if ($settlement == $current_domain) {
                    $routing->setRoute($route, $domain);
                    waRequest::setParam($route);
                }
                $settlements[] = $settlement;
            }
        }

        $this->view->assign('settlements', $settlements);

        $path = shopYandexmarketPlugin::path();
        $info = array();
        $info['exists'] = file_exists($path);
        $info['mtime'] = $info['exists'] ? filemtime($path) : null;
        $route_params = array(
            'plugin' => 'yandexmarket',
            'hash'   => shopYandexmarketPlugin::uuid(),
        );
        $info['url'] = $info['exists'] ? $routing->getUrl('shop/frontend/catalog', $route_params, true) : null;
        $this->view->assign('info', $info);
        /**
         * @var shopConfig $config;
         */
        $config =  wa('shop')->getConfig();
        $this->view->assign('primary_currency',$config->getCurrency());

        $this->view->assign('company', $this->plugin()->getSettings('company'));

        $type_model = new shopTypeModel();
        $this->view->assign('types', $type_model->getAll());

        $set_model = new shopSetModel();
        $this->view->assign('sets', $set_model->getAll());

        $this->view->assign('map', $this->plugin()->map(waRequest::post('map'), true));
        $this->view->assign('map_categories', $this->plugin()->categories());

        $feature_model = new shopFeatureModel();
        $this->view->assign('features', $feature_model->getFeatures(true));

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
            $plugin = wa()->getPlugin('yandexmarket');
        }
        return $plugin;
    }
}
