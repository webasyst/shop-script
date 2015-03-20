<?php
/**
 * Customers report in Reports section.
 */
class shopReportsCustomersAction extends waViewAction
{
    public function execute()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        list($start_date, $end_date, $group_by, $request_options) = shopReportsSalesAction::getTimeframeParams();
        $storefront = waRequest::request('storefront', null, 'string');
        $model_options = array(
            'sort' => '!sales',
        );
        if ($storefront) {
            $request_options['storefront'] = $storefront;
            $model_options['storefront'] = $storefront;
        }
        $sort = waRequest::request('sort', '!profit', 'string');

        $def_cur = wa()->getConfig()->getCurrency();

        // Load data for sortable table of sources
        $sales_model = new shopSalesModel();
        $table_data = $sales_model->getPeriodCustomers($start_date, $end_date, $model_options + array(
            'order' => $sort,
            'start' => waRequest::request('start', 0, 'int'),
            'limit' => 100500,
        ), $total_rows);
        $more_rows_exist = $total_rows > count($table_data);
        $model_options['ensured'] = true;

        // For each row in $table_data tell if it's a referrer source or UTM campaign
        $is_campaign = array();
        foreach(shopReportsmarketingcostsActions::getCampaigns() as $c) {
            $is_campaign[$c['name']] = true;
        }
        foreach($table_data as &$row) {
            $row['is_campaign'] = $row['name'] && !empty($is_campaign[$row['name']]);
        }
        unset($row);

        // Data for countries table
        $country_customers = $sales_model->getCustomersByCountry($start_date, $end_date, $model_options);

        // Data for map chart
        $map_chart_data = array();
        foreach($country_customers as $c) {
            if ($c['iso3letter']) {
                $map_chart_data[] = array(
                    'name' => strtoupper($c['iso3letter']),
                    'country_name' => $c['name'],
                    'value' => $c['customers'],
                    'hint' => _w('%d customer', '%d customers', $c['customers']),
                );
            }
        }

        $country_names = array();
        foreach(wao(new waCountryModel())->all() as $c) {
            $country_names[$c['iso3letter']] = $c['name'];
        }

        $this->view->assign(array(
            'total' => self::getTotals($table_data),
            'storefronts' => shopReportsSalesAction::getStorefronts(),
            'country_customers' => $country_customers,
            'request_options' => $request_options,
            'more_rows_exist' => $more_rows_exist,
            'map_chart_data' => $map_chart_data,
            'country_names' => $country_names,
            'table_data' => $table_data,
            'def_cur' => $def_cur,
        ));
    }

    protected static function getTotals($table_data)
    {
        // Whole-period totals
        $totals = array(
            'order_count' => 0,
            'customers_count' => 0,
            'new_customer_count' => 0,
            'lifetime_customers_count' => 0,
            'lifetime_profit' => 0,
            'lifetime_sales' => 0,
            'lifetime_cost' => 0,
            'profit' => 0,
            'sales' => 0,
            'cost' => 0,
        );
        foreach($table_data as $row) {
            foreach($totals as $k => $v) {
                $totals[$k] += $row[$k];
            }
        }

        if ($totals['customers_count'] > 0) {
            $totals['cac'] = $totals['cost'] / $totals['customers_count'];
            $totals['ampu'] = $totals['profit'] / $totals['customers_count'];
            $totals['arpu'] = $totals['sales'] / $totals['customers_count'];
        } else {
            $totals['cac'] = 0;
            $totals['ampu'] = 0;
            $totals['arpu'] = 0;
        }

        if ($totals['lifetime_customers_count'] > 0) {
            $totals['lifetime_cac'] = $totals['lifetime_cost'] / $totals['lifetime_customers_count'];;
            $totals['lifetime_arpu'] = $totals['lifetime_sales'] / $totals['lifetime_customers_count'];
            $totals['lifetime_ampu'] = $totals['lifetime_profit'] / $totals['lifetime_customers_count'];
        } else {
            $totals['lifetime_cac'] = 0;
            $totals['lifetime_arpu'] = 0;
            $totals['lifetime_ampu'] = 0;
        }
        $totals['ltv'] = $totals['lifetime_ampu'];

        if ($totals['cost']) {
            $totals['roi'] = round($totals['profit'] * 100 / $totals['cost']);
        } else {
            $totals['roi'] = 0;
        }

        if ($totals['lifetime_cost']) {
            $totals['lifetime_roi'] = round($totals['lifetime_profit'] * 100 / $totals['lifetime_cost']);
        } else {
            $totals['lifetime_roi'] = 0;
        }

        return $totals;
    }
}

