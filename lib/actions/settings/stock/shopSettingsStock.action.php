<?php

class shopSettingsStockAction extends waViewAction
{
    public function execute()
    {
        $stocks = shopHelper::getStocks();
        $stock_rules_model = new shopStockRulesModel();
        $rules = $stock_rules_model->getRules();
        list($plugins_html, $rule_condition_types) = $this->processEvent($stocks, $rules);
        $rule_groups = $stock_rules_model->prepareRuleGroups($rules);

        $app_settings_model = new waAppSettingsModel();
        if ($app_settings_model->get('shop', 'disable_stock_count')) {
            $stock_counting_action = 'none';
        } elseif ($app_settings_model->get('shop', 'update_stock_count_on_create_order')) {
            $stock_counting_action = 'create';
        } else {
            $stock_counting_action = 'processing';
        }


        $this->view->assign(array(
            'stocks'                 => $stocks,
            'stock_counting_action'  => $stock_counting_action,
            'plugins_html'           => $plugins_html,
            'rule_condition_types'   => $rule_condition_types,
            'storefront_rule_groups' => self::getStorefrontRuleGroups($stocks),
            'rule_groups'            => $rule_groups,
        ));
    }

    protected static function getStorefrontRuleGroups($stocks)
    {
        $result = array();
        foreach (wa()->getRouting()->getByApp('shop') as $domain => $routes) {
            foreach ($routes as $r) {
                if (!empty($r['stock_id']) && !empty($stocks[$r['stock_id']])) {
                    $result[] = array(
                        'storefront' => $domain.'/'.rtrim($r['url'], '*'),
                        'stock_name' => $stocks[$r['stock_id']]['name'],
                    );
                }
            }
        }
        return $result;
    }

    protected function processEvent($stocks, &$rules)
    {
        /**
         * @event backend_settings_stocks
         *
         * Hook for backend stocks settings page.
         *
         * Plugins are expected to return one item or a list of items to to add
         * to available rule conditions. Items have the following structure (all fields are required):
         * array(
         *   'id'            => string, // Rule type id to save to shop_stock_rules.rule_type.
         *                              // Must be prefixed with plugin id + underscore.
         *   'name'          => string, // Localized human-readable name to show in selector.
         *   'init_html'     => string, // HTML to append to page in a hidden block.
         * )                            // Useful for JS initialization scripts.
         *
         * See also `frontend_checkout_stock_rules` event for how stock selection rules are applied in frontend.
         *
         * @param array $params
         * @param array [array] $params['rules'] list of rows from shop_stock_rules table.
         * @param array [array] $params['stocks'] same as shopHelper::getStocks()
         * @return array
         * @return array[string] $result['custom_html'] additional HTML to append to page, not tied to any rule condition type
         */
        $params = array('rules' => &$rules, 'stocks' => $stocks);
        $event_result = array('shop' => $this->getBuiltinTypes($params));
        $event_result += wa('shop')->event('backend_settings_stocks', $params);
        $plugins_html = $rule_condition_types = array();
        foreach ($event_result as $plugin_id => $data) {
            if (isset($data['custom_html'])) {
                $plugins_html[$plugin_id] = $data['custom_html'];
                unset($data['custom_html']);
            }
            if (isset($data['id'])) {
                $data = array($data);
            }
            foreach ($data as $ct) {
                if (!is_array($ct) || empty($ct['id']) || empty($ct['name']) || empty($ct['init_html'])) {
                    if (waSystemConfig::isDebug()) {
                        waLog::log("backend_settings_stocks: something's wrong with output from ".$plugin_id, 'event.log');
                        waLog::dump($ct, 'event.log');
                    }
                    continue;
                }
                if (isset($ct['init_html'])) {
                    $plugins_html[$plugin_id.'#'.$ct['id']] = $ct['init_html'];
                    unset($ct['init_html']);
                }
                $rule_condition_types[$ct['id']] = $ct;
            }
        }

        return array($plugins_html, $rule_condition_types);
    }

    protected function getBuiltinTypes(&$params)
    {
        $template_dir = str_replace('SettingsStock.html', '', $this->getTemplate());

        // Fetch HTML for by_shipping condition type
        $model = new shopPluginModel();
        $this->view->assign(array(
            'shipping_types' => $model->listPlugins(shopPluginModel::TYPE_SHIPPING, array('all' => true,)),
        ));
        $by_shipping_html = $this->view->fetch($template_dir.'SettingsStockByShipping.html');

        // Countries and regions data for by_region condition type
        $country_model = new waCountryModel();
        $countries = array();
        foreach ($country_model->all() as $c) {
            $countries[$c['iso3letter']] = array(
                'id'      => $c['iso3letter'],
                'name'    => $c['name'],
                'regions' => array(),
            );
        }
        $region_model = new waRegionModel();
        $regions_by_country = array();
        foreach ($region_model->getAll() as $r) {
            $regions_by_country[$r['country_iso3']][$r['code']] = $r;
        }
        foreach ($regions_by_country as $country_id => $regions) {
            if (empty($countries[$country_id])) {
                continue;
            }
            foreach ($region_model->getByCountryWithFav(array_values($regions)) as $r) {
                $countries[$country_id]['regions'][$r['code']] = array(
                    'id'   => $r['code'],
                    'name' => $r['name'],
                );
            }
            $countries[$country_id]['regions'] = array_values($countries[$country_id]['regions']);
        }

        // Fetch HTML for by_region condition type
        $this->view->assign(array(
            'countries' => $countries,
        ));
        $by_region_html = $this->view->fetch($template_dir.'SettingsStockByRegion.html');

        return array(
            array(
                'id'        => 'by_shipping',
                'name'      => _w('By shipping option'),
                'init_html' => $by_shipping_html,
            ),
            array(
                'id'        => 'by_region',
                'name'      => _w('By shipping location'),
                'init_html' => $by_region_html,
            ),
        );
    }
}
