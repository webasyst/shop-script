<?php

class shopMarketingPromosAction extends shopMarketingViewAction
{
    public function execute()
    {
        $sort = waRequest::get('sort', [], waRequest::TYPE_ARRAY_TRIM);
        $status = waRequest::get('status', null, waRequest::TYPE_STRING_TRIM);
        $page = waRequest::get('page', 1, waRequest::TYPE_INT);
        if ($page < 1) {
            $page = 1;
        }

        $storefront_filter = waRequest::get('storefront', null, waRequest::TYPE_STRING_TRIM);

        $storefronts = shopStorefrontList::getAllStorefronts();

        $list_params = array(
            'with_images' => true
        );

        $unattached_filter_active = $storefront_filter == '_unattached_';
        if ($unattached_filter_active) {
            $list_params['show_unattached'] = true;
        } else {
            if ($storefront_filter && (empty($storefronts) || !in_array($storefront_filter, $storefronts))) {
                $storefront_filter = null;
            }
            if (!$storefront_filter && count($storefronts) === 1) {
                $storefront_filter = current($storefronts);
            }

            $list_params['storefront'] = $storefront_filter;
        }

        $promo_model = new shopPromoModel();
        $active_promos = [];
        if (empty($status) || $status == shopPromoModel::STATUS_ACTIVE) {
            $active_promos = $promo_model->getList(array_merge($list_params, ['status' => shopPromoModel::STATUS_ACTIVE]));
        }

        $promos_charts = $this->loadCharts($active_promos);

        foreach ($active_promos as &$p) {
            $p['period_percent'] = self::calculatePeriodPercent(
                ifempty($p['start_datetime'], $p['create_datetime']),
                $p['finish_datetime']
            );
        }
        unset($p);

        $planned_promos = [];
        if (empty($status) || $status == shopPromoModel::STATUS_PLANNED) {
            $planned_promos = $promo_model->getList(array_merge($list_params, ['status' => shopPromoModel::STATUS_PLANNED]));
        }

        $total_completed_promos = 0;
        $completed_promos = [];
        if (empty($status) || $status == shopPromoModel::STATUS_COMPLETED) {
            $list_params['limit'] = $this->getConfig()->getOption('promos_per_page');
            $list_params['offset'] = max(0, $page - 1) * $list_params['limit'];
            $list_params['status'] = shopPromoModel::STATUS_COMPLETED;

            if (!empty($sort['field'])) {
                $list_params['sort'] = $sort;
            }

            $completed_promos = $promo_model->getList($list_params, $total_completed_promos);
        }

        $promo_ids = array_merge(
            array_keys($active_promos),
            array_keys($planned_promos),
            array_keys($completed_promos)
        );

        // Promo stats (profit, ROI, order count)
        $promo_orders_model = new shopPromoOrdersModel();
        $promos_stats = $promo_orders_model->getBasicStats($promo_ids);

        // Marker icons
        $promos_markers = self::getMarkers($promo_ids);

        $show_unattached_storefronts_selector = $promo_model->countUnattachedStorefronts() > 0;

        // params for backend_marketing_promos event
        $additional_html = $this->backendMarketingPromosEvent(ref([
            'show_unatt_storefronts' => &$show_unattached_storefronts_selector,
            'unattached_active'      => &$unattached_filter_active,
            'active_promos'          => &$active_promos,
            'planned_promos'         => &$planned_promos,
            'completed_promos'       => &$completed_promos,
            'promos_charts'          => &$promos_charts,
            'promos_markers'         => &$promos_markers,
            'promos_stats'           => &$promos_stats,
            'storefronts'            => &$storefronts,

            'filtering' => [
                'storefront'         => $storefront_filter,
                'status'             => $status,
                'sort'               => $sort,
                'page'               => $page,
            ],
        ]));

        $this->view->assign(array(
            'show_unatt_storefronts' => $show_unattached_storefronts_selector,
            'unattached_active'      => $unattached_filter_active,
            'active_promos'          => $active_promos,
            'planned_promos'         => $planned_promos,
            'completed_promos'       => $completed_promos,
            'total_completed_promos' => $total_completed_promos,
            'storefronts'            => $storefronts,
            'active_storefront'      => $storefront_filter,
            'promos_charts'          => $promos_charts,
            'promos_markers'         => $promos_markers,
            'promos_stats'           => $promos_stats,
            'additional_html'        => $additional_html,
            'status'                 => $status,
            'sort'                   => $sort,
            'page'                   => $page,
        ));
    }

    protected function backendMarketingPromosEvent(&$params)
    {
        /**
         * List of promos in Marketing section.
         * Hook allows to modify data before sending to template for rendering,
         * as well as add custom HTML to the page.
         *
         * @event backend_marketing_promos
         * @param array [string]mixed $params
         * @param array [string]array $params['active_promos']    list of currently running promos
         * @param array [string]array $params['planned_promos']   planned promos
         * @param array [string]array $params['completed_promos'] finished promos
         * @param array [string]array $params['promos_charts']    data for charts for active promos
         * @param array [string]array $params['promos_markers']   markers (icons) for all promos
         * @param array [string]array $params['promos_stats']     statistics for all promos
         * @param array [string]array $params['storefronts']      list of existing storefronts
         * @param array [string]array $params['filtering']        filter settings currently applied to list (read-only)
         *
         * @return array[string][string]string $return[%plugin_id%]['action_link'] return html output here to add a link at the top right of the page
         * @return array[string][string]string $return[%plugin_id%]['bottom'] html output to include at the bottom of the page
         */
        $event_result = wa()->event('backend_marketing_promos', $params);

        $additional_html = [
            'action_link' => [],
            'bottom' => [],
        ];
        foreach($event_result as $res) {
            if (!is_array($res)) {
                $res = [
                    'bottom' => $res,
                ];
            }
            foreach($res as $k => $v) {
                if (isset($additional_html[$k])) {
                    if (!is_array($v)) {
                        $v = [$v];
                    }
                    foreach($v as $html) {
                        $additional_html[$k][] = $html;
                    }
                }
            }
        }

        return $additional_html;
    }

    protected function loadCharts($active_promos)
    {
        $start_date = date('Y-m-d', time() - 3600*24*7);
        $end_date = date('Y-m-d');

        $sales_model = new shopSalesModel();
        $promos_charts = $sales_model->getPeriodByDate('promos', $start_date, $end_date, [
            'date_group' => 'days',
            'group_by_name' => true,
            'filter' => [
                'name' => array_keys($active_promos),
            ],
        ]);

        foreach($promos_charts as &$chart) {
            $chart = array_values($chart);
            foreach($chart as &$point) {
                $point = [
                    'date' => $point['date'],
                    'value' => $point['sales'],
                ];
            }
        }
        unset($chart, $point);

        return $promos_charts + array_map(function($p) {
            return [];
        }, $active_promos);
    }

    public static function calculatePeriodPercent($start_datetime, $end_datetime)
    {
        if (empty($start_datetime) || empty($end_datetime)) {
            return 0;
        }
        $start = strtotime($start_datetime);
        $finish = strtotime($end_datetime);
        return (time() - $start)*100/($finish - $start);
    }

    protected function getMarkers($promo_ids)
    {
        $promo_rules_model = new shopPromoRulesModel();
        $promo_available_types = $promo_rules_model->getAvailableTypes();
        $promo_rule_types = $promo_rules_model->getTypesByPromos($promo_ids);

        $result = [];
        foreach($promo_rule_types as $promo_id => $rule_types) {
            foreach($rule_types as $type) {
                if (!empty($promo_available_types[$type])) {
                    $result[$promo_id][$type] = $promo_available_types[$type];
                }
            }
        }

        return $result;
    }

}