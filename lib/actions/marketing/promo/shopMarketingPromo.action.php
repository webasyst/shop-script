<?php

class shopMarketingPromoAction extends shopMarketingViewAction
{
    public function execute()
    {
        $promo_model = new shopPromoModel();
        $promo_id = waRequest::param('promo_id', null, waRequest::TYPE_INT);

        // Custom options, e.g. for tools
        $options = waRequest::get('options', null, waRequest::TYPE_ARRAY_TRIM);

        if (empty($promo_id)) {
            $promo = $promo_model->getEmptyRow();
            $promo['routes'] = $promo['rules'] = array();
        } else {
            $promo = $promo_model->getPromo($promo_id);
        }

        $storefronts = shopStorefrontList::getAllStorefronts();
        if (empty($promo['id']) || (!empty($promo['routes'][shopPromoRoutesModel::FLAG_ALL]))) {
            $storefronts_count = count($storefronts);
        } else {
            if (!empty($promo['routes'][shopPromoRoutesModel::FLAG_ALL . '/'])) {
                $count_auxiliary_routes = 2;
            } else {
                $count_auxiliary_routes = !empty($promo['routes'][shopPromoRoutesModel::FLAG_ALL]) ? 1 : 0;
            }
            $storefronts_count = count($promo['routes']) - $count_auxiliary_routes;
        }

        $all_storefronts_checked = true;
        foreach ($storefronts as $storefront) {
            if (empty($promo["routes"][$storefront])) {
                $all_storefronts_checked = false;
            }
        }

        list($start_date, $end_date, $group_by) = self::getTimeframeLimits($promo);

        // Data for chart
        $chart_data = [];
        if ($promo_id > 0) {
            $sales_model = new shopSalesModel();
            $raw_data_promo = $sales_model->getPeriodByDate('promos', $start_date, $end_date, [
                'date_group' => $group_by,
                'filter'     => [
                    'name' => $promo_id,
                ],
            ]);

            $period_options = [
                'date_group' => $group_by,
            ];
            $promo_routes = array_keys($promo['routes']);
            if (!empty($promo_routes) && !in_array(shopPromoRoutesModel::FLAG_ALL, $promo_routes)) {
                $period_options['storefront'] = $promo_routes;
            }
            $raw_data_sales = $sales_model->getPeriodByDate('sources', $start_date, $end_date, $period_options);
            list($chart_data, $promo_totals, $overall_totals) = self::getChartData($raw_data_promo, $raw_data_sales);

            $expense_model = new shopExpenseModel();
            $promo_totals['expense'] = (float)$expense_model->calculateTotal([
                'type' => 'promo',
                'name' => $promo_id,
            ]);

            $promo_totals['roi'] = null;
            if ($promo_totals['expense'] > 0) {
                $promo_totals['roi'] = $promo_totals['profit'] * 100 / $promo_totals['expense'];
                $promo_totals['roi'] = round($promo_totals['roi']);
            }
        }

        $overall_totals = ifempty($overall_totals);
        $promo_totals = ifempty($promo_totals);
        $chart_data = ifempty($chart_data);

        $available_rule_types = $this->getAvailableRuleTypes();

        $additional_html = $this->backendMarketingPromoEvent(ref([
            'options'        => &$options,
            'promo'          => &$promo,
            'overall_totals' => &$overall_totals,
            'promo_totals'   => &$promo_totals,
            'chart_data'     => &$chart_data,
            'storefronts'    => &$storefronts,
            'rule_types'     => &$available_rule_types,
        ]));

        $has_additional_html = false;
        foreach ($additional_html as $item) {
            if ($item) {
                $has_additional_html = true;
                break;
            }
        }

        $this->view->assign(array(
            'promo'                   => $promo,
            'storefronts'             => $storefronts,
            'storefronts_count'       => $storefronts_count,
            'all_storefronts_checked' => $all_storefronts_checked,
            'available_rule_types'    => $available_rule_types,
            'options'                 => $options,
            'overall_totals'          => $overall_totals,
            'promo_totals'            => $promo_totals,
            'chart_data'              => $chart_data,
            'has_additional_html'     => $has_additional_html,
            'additional_html'         => $additional_html,
        ));
    }

    protected function backendMarketingPromoEvent(&$params)
    {
        /**
         * Single promo page in marketing section.
         * Hook allows to modify data before sending to template for rendering,
         * as well as add custom HTML to the page.
         *
         * @event backend_marketing_promo
         * @param array [string]mixed $params
         * @param array [string]array $params['options']         Custom options from GET, e.g. for tools
         * @param array [string]array $params['promo']           Basic promo data
         * @param array [string]array $params['overall_totals']  Store stats for the period
         * @param array [string]array $params['promo_totals']    Promo stats for the period
         * @param array [string]array $params['chart_data']      Data for chart
         * @param array [string]array $params['storefronts']     List of existing storefronts
         * @param array [string]array $params['rule_types']      List of promo tools (rule types), see promo_rule_types event
         *
         * @return array[string][string]string $return[%plugin_id%]['action_link']   return HTML here to add a link at the top right of the page
         * @return array[string][string]string $return[%plugin_id%]['info_section']  return HTML here to show as part of promo editor form
         * @return array[string][string]string $return[%plugin_id%]['button']        return HTML here to add something in the floating button bar below content
         * @return array[string][string]string $return[%plugin_id%]['bottom']        return custom HTML to include at the bottom of the page
         *
         * @see events: promo_rule_types, promo_save, backend_marketing_promo_orders, backend_marketing_promo_expenses
         */
        $event_result = wa()->event('backend_marketing_promo', $params);

        $additional_html = [
            'action_link'  => [],
            'info_section' => [],
            'button'       => [],
            'bottom'       => [],
        ];
        foreach ($event_result as $res) {
            if (!is_array($res)) {
                $res = [
                    'bottom' => $res,
                ];
            }
            foreach ($res as $k => $v) {
                if (isset($additional_html[$k])) {
                    if (!is_array($v)) {
                        $v = [$v];
                    }
                    foreach ($v as $html) {
                        $additional_html[$k][] = $html;
                    }
                }
            }
        }

        return $additional_html;
    }

    protected function getAvailableRuleTypes()
    {
        $promo_rules_model = new shopPromoRulesModel();
        $promo_available_types = $promo_rules_model->getAvailableTypes();
        if (wa()->whichUI() !== '1.3') {
            foreach ($promo_available_types as &$type) {
                if (isset($type['css_class'])) {
                    $type['css_class'] = wa()->getView()->getHelper()->shop->convertIcon($type['css_class']);
                }
            }
        }

        return $promo_available_types;
    }

    public static function getTimeframeLimits($promo)
    {
        $start_ts = time() - 30 * 24 * 3600;
        $start_date = date('Y-m-d', $start_ts);
        if (!empty($promo['start_datetime'])) {
            $start_date = explode(' ', $promo['start_datetime'])[0];
            $start_ts = strtotime($start_date);
        } else {
            if (!empty($promo['create_datetime'])) {
                $start_date = explode(' ', $promo['create_datetime'])[0];
                $start_ts = strtotime($start_date);
            }
        }

        $finish_ts = time();
        $finish_date = date('Y-m-d', $finish_ts);
        if (!empty($promo['finish_datetime'])) {
            $finish_date = explode(' ', $promo['finish_datetime'])[0];
            $finish_ts = strtotime($finish_date);
        }

        if ($finish_ts - $start_ts >= 365 * 24 * 3600) {
            $group_by = 'months';
        } else {
            $group_by = 'days';
        }

        return [$start_date, $finish_date, $group_by];
    }

    protected static function getChartData($raw_data_promo, $raw_data_sales)
    {
        $overall_totals = $promo_totals = [
            'order_count'        => 0,
            'new_customer_count' => 0,
            'profit'             => 0,
            'sales'              => 0,
            'purchase'           => 0,
            'shipping'           => 0,
            'tax'                => 0,
            'cost'               => 0,
        ];

        $chart_data = array_map(function ($d, $d_total) use (&$overall_totals, &$promo_totals) {

            foreach ($d as $k => $v) {
                if ($k != 'date') {
                    $promo_totals[$k] = ifset($promo_totals, $k, 0) + $v;
                    $overall_totals[$k] = ifset($overall_totals, $k, 0) + $d_total[$k];
                }
            }

            return [
                'date'        => $d['date'],
                'sales'       => ifset($d['sales'], 0),
                'total_sales' => ifset($d_total['sales'], 0),
            ];
        }, $raw_data_promo, $raw_data_sales);

        foreach ([&$overall_totals, &$promo_totals] as &$totals) {
            $totals['avg_order'] = 0;
            if ($totals['order_count'] > 0) {
                $totals['avg_order'] = $totals['sales'] / $totals['order_count'];
            }
        }
        unset($totals);

        foreach (['sales', 'order_count', 'avg_order'] as $k) {
            $promo_totals[$k.'_percent'] = 0;
            if ($overall_totals[$k] > 0) {
                $promo_totals[$k.'_percent'] = $promo_totals[$k] * 100 / $overall_totals[$k];
            }
        }

        return [$chart_data, $promo_totals, $overall_totals];
    }
}
