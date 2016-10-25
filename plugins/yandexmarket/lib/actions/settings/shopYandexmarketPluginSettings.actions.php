<?php

class shopYandexmarketPluginSettingsActions extends waViewActions
{
    private $plugin_id = 'yandexmarket';
    /**
     * @var shopYandexmarketPlugin
     */
    private $plugin;

    protected function preExecute()
    {
        if (!$this->getUser()->getRights('shop', 'settings')) {
            throw new waException(_w('Access denied'));
        }
        $this->plugin = wa('shop')->getPlugin($this->plugin_id, true);
    }

    public function postExecute()
    {
        waSystem::popActivePlugin();
    }

    public function defaultAction()
    {
        $plugin_id = 'yandexmarket';
        $vars = array();
        $plugins = $this->getConfig()->getPlugins();
        if (isset($plugins[$plugin_id])) {
            $namespace = wa()->getApp().'_'.$plugin_id;

            $params = array();
            $params['id'] = $plugin_id;
            $params['namespace'] = $namespace;
            $params['title_wrapper'] = '%s';
            $params['description_wrapper'] = '<br><span class="hint">%s</span>';
            $params['control_wrapper'] = '<div class="name">%s</div><div class="value">%s %s</div>';

            $settings_controls = $this->plugin->getControls($params);
            $this->getResponse()->setTitle(_w(sprintf('Plugin %s settings', $this->plugin->getName())));

            $vars['plugin_info'] = $plugins[$plugin_id];

            $vars['plugin_id'] = $plugin_id;
            $vars['settings_controls'] = $settings_controls;

            $this->campaignsAction();
        }
        $this->view->assign($vars);
    }

    public function campaignsAction()
    {
        $options = array(
            'balance' => false,
            'orders'  => false,
            'offers'  => false,
            'outlets' => false,
        );
        try {
            $cpa_available = $this->plugin->checkCpa();
            $campaigns = $this->plugin->getCampaigns($options);
        } catch (waException $ex) {
            $error = $ex->getMessage();
            $error_code = $ex->getCode();
        }
        $this->view->assign(compact('campaigns', 'cpa_available', 'error', 'error_code'));
    }

    public function campaignAction()
    {
        /**
         * @var shopConfig $config ;
         */
        $config = wa('shop')->getConfig();

        $campaign_id = waRequest::request('campaign_id', null, waRequest::TYPE_INT);
        $cpa_available = $this->plugin->checkCpa();
        $this->view->assign('cpa_available', $cpa_available);

        $api_available = $this->plugin->checkApi();
        $this->view->assign('api_available', $api_available);
        $this->view->assign('api_net', class_exists('waNet'));


        $this->view->assign('campaign_id', $campaign_id);

        if ($cpa_available) {
            $model = new shopYandexmarketCampaignsModel();
            $campaign = $model->get($campaign_id);
            $this->view->assign('campaign', $campaign);

            $this->view->assign('app_settings', waSystem::getSetting(null));
            $shipping_methods = array();
            $primary_currency = $config->getCurrency();

            try {
                if ($api_available && ($region = $this->plugin->getCampaignRegion($campaign_id))) {
                    $address = shopYandexmarketPluginOrder::parseAddress($region, null, true);
                    $this->view->assign('address', $address);

                    $items = array(
                        array(
                            'weight'   => 1.0,//base unit - kg
                            'price'    => 1,
                            'quantity' => 1,
                        ),
                    );

                    $primary_currency = $this->plugin->getSettings('primary_currency');

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
                    if (empty($campaign['local_delivery_only']) || true) {
                        $address = null;
                    } else {
                        $address = $address['data'];
                    }
                    $shipping_methods = shopHelper::getShippingMethods($address, $items, $shipping_params);
                    $shipping_methods = array_filter($shipping_methods, create_function('$m', 'return empty($m["external"]);'));


                }
            } catch (waException $ex) {
                $this->view->assign('address_error', $ex->getMessage());
            }

            $settings = isset($campaign['shipping_methods']['dummy']) ? $campaign['shipping_methods']['dummy'] : array();

            $dummy = array(
                'rate'     => ifset($settings['cost']),
                'name'     => ifset($settings['name'], 'Курьер'),
                'estimate' => ifset($settings['estimate']),
                'currency' => $primary_currency,
            );


            $this->view->assign('shipping_methods', compact('dummy') + $shipping_methods);
            $plugin_model = new shopPluginModel();

            $fields = array(
                'plugin' => 'yandexmoney',
                'type'   => shopPluginModel::TYPE_PAYMENT,
                'status' => 1,
            );

            $payment_methods = $plugin_model->getByField($fields, true);

            $this->view->assign('payment_methods', $payment_methods);

            $this->view->assign('primary_currency', $primary_currency);


            $routing = wa()->getRouting();
            $route_params = array(
                'plugin' => $this->plugin_id,
                'module' => 'api',
                'action' => 'hello',
            );
            $this->view->assign('api_url', preg_replace('@^http://@', 'https://', $routing->getUrl('shop', $route_params, true)));
            $this->view->assign('timezones', waDateTime::getTimeZones());
        }
    }

    public function outletsAction()
    {
        $campaign_id = waRequest::get('campaign_id');

        try {
            $outlets = $this->plugin->getOutlets($campaign_id);
        } catch (waException $ex) {
            $error = $ex->getMessage();
            $error_code = $ex->getCode();
        }
        $this->view->assign(compact('campaign_id', 'outlets', 'error', 'error_code'));
    }

    protected function getTemplate()
    {
        $module = 'settings';
        $template = parent::getTemplate();
        $search = sprintf('/%s/%s', $this->plugin_id, ucfirst($this->plugin_id));
        $replace = sprintf('/%s/%s', $module, ucfirst($module));
        $template = str_replace($search, $replace, $template);
        return $template;
    }
}
