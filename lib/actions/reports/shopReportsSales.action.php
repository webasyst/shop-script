<?php
/**
 * Sales report in Reports section, and all #/sales/type=??? links in sidebar.
 * Serves lazy-loading for table data there, too.
 */
class shopReportsSalesAction extends waViewAction
{
    public function execute()
    {
        shopReportsSalesAction::jsRedirectIfDisabled();

        // Get parameters from GET/POST
        list($start_date, $end_date, $group_by, $request_options) = self::getTimeframeParams();

        $model_options = array();
        $storefront = waRequest::request('storefront', null, 'string');
        if ($storefront) {
            $request_options['storefront'] = $storefront;
            $model_options['storefront'] = $storefront;
        }
        $sort = waRequest::request('sort', '!profit', 'string');
        $request_options['sort'] = $sort;

        $type_id = waRequest::request('type', 'sources', 'string');
        $request_options['type'] = $type_id;
        $roi_enabled = in_array($type_id, array('sources', 'social', 'campaigns', 'customer_sources'));

        $abtest_id = waRequest::request('abtest', '', 'int');
        if ($abtest_id) {
            $request_options['abtest'] = $abtest_id;
        }

        $sales_model = new shopSalesModel();
        if ($roi_enabled) {
            $min_date = $sales_model->getMinDate();
            $max_date = date('Y-m-d 23:59:59');
            $sales_model->ensurePeriod($type_id, $min_date, $max_date, $model_options);
            $model_options['ensured'] = true;
        }

        $def_cur = wa()->getConfig()->getCurrency();

        // Load data for table below main chart
        $table_data = $sales_model->getPeriod($type_id, $start_date, $end_date, $model_options + array(
            'order' => $sort,
            'start' => waRequest::request('start', 0, 'int'),
        ), $total_rows);
        $more_rows_exist = $total_rows > count($table_data);
        $model_options['ensured'] = true;

        // Calculate lifetime ROI
        if ($roi_enabled) {
            $names = array();
            foreach($table_data as $k => $v) {
                $names[$v['name']] = $k;
            }

            $lifetime_sales = array();
            $rows = $sales_model->getPeriod($type_id, $min_date, $max_date, array(
                'limit' => 100500,
                'names' => array_keys($names),
            ) + $model_options);
            foreach($rows as $row) {
                $lifetime_sales[$row['name']] = $row;
            }

            foreach($table_data as &$row) {
                $row['lifetime_roi'] = round(ifset($lifetime_sales[$row['name']]['roi'], 0));
            }
            unset($row);
        }

        // Load data for A/B tests
        $abtest_variants = array();
        if ($abtest_id) {
            $opts = array( 'abtest_id' => $abtest_id );
            if ($more_rows_exist) {
                $opts['names'] = array();
                foreach($table_data as $row) {
                    $opts['names'][] = $row['name'];
                }
            }

            $abtest_variants_model = new shopAbtestVariantsModel();
            $abtest_variants = $abtest_variants_model->getByField('abtest_id', $abtest_id, 'id');

            // make a separate request for each abtest variant
            foreach($abtest_variants as &$v) {
                $opts['abtest_variant_id'] = $v['id'];
                $v['data'] = array();
                foreach($sales_model->getPeriod($type_id, $start_date, $end_date, $model_options + $opts) as $row) {
                    $v['data'][$row['name']] = $row;
                }
            }
            unset($v);
        }

        $this->prepareTableData($type_id, $table_data);

        // For lazy-loading and sorting return just the table alone
        if (waRequest::request('table_only')) {
            $this->setTemplate('templates/actions/reports/sales_report_rows.html');
            $this->view->assign(array(
                'more_rows_exist' => $more_rows_exist,
                'abtest_variants' => $abtest_variants,
                'roi_enabled' => $roi_enabled,
                'table_data' => $table_data,
                'def_cur' => $def_cur,
            ));
            return;
        }

        // Whole-period totals, all in default currency
        $totals = $sales_model->getTotals($type_id, $start_date, $end_date, $model_options);
        if ($abtest_variants) {
            foreach($abtest_variants as &$v) {
                $opts['abtest_variant_id'] = $v['id'];
                $v['totals'] = $sales_model->getTotals($type_id, $start_date, $end_date, $model_options + $opts);
                $v['totals']['cost_formatted'] = waCurrency::format('%{h}', $v['totals']['cost'], $def_cur);
                $v['totals']['sales_formatted'] = waCurrency::format('%{h}', $v['totals']['sales'], $def_cur);
                $v['totals']['profit_formatted'] = waCurrency::format('%{h}', $v['totals']['profit'], $def_cur);
                $v['totals']['avg_day_formatted'] = waCurrency::format('%{h}', $v['totals']['avg_day'], $def_cur);
                $v['totals']['avg_order_formatted'] = waCurrency::format('%{h}', $v['totals']['avg_order'], $def_cur);
            }
            unset($v);
        }
        if ($roi_enabled) {
            $alltime_totals = $sales_model->getTotals($type_id, $min_date, $max_date, $model_options);
            $totals['lifetime_roi'] = round($alltime_totals['roi']);
        }

        // Formatted currency amounts for template
        $totals['cost_formatted'] = waCurrency::format('%{h}', $totals['cost'], $def_cur);
        $totals['sales_formatted'] = waCurrency::format('%{h}', $totals['sales'], $def_cur);
        $totals['profit_formatted'] = waCurrency::format('%{h}', $totals['profit'], $def_cur);
        $totals['avg_day_formatted'] = waCurrency::format('%{h}', $totals['avg_day'], $def_cur);
        $totals['avg_order_formatted'] = waCurrency::format('%{h}', $totals['avg_order'], $def_cur);

        // Data for main chart
        $graph_data = self::getGraphData($sales_model->getPeriodByDate($type_id, $start_date, $end_date, $model_options + array(
            'date_group' => $group_by,
        )));

        // All abtests for the period for selector
        $abtests = $sales_model->getAvailableABtests($start_date, $end_date, $model_options);

        $this->view->assign(array(
            'storefronts' => self::getStorefronts(),
            'request_options' => $request_options,
            'more_rows_exist' => $more_rows_exist,
            'abtest_variants' => $abtest_variants,
            'menu_types' => self::getMenuTypes(),
            'roi_enabled' => $roi_enabled,
            'graph_data' => $graph_data,
            'table_data' => $table_data,
            'group_by' => $group_by,
            'abtests' => $abtests,
            'def_cur' => $def_cur,
            'totals' => $totals,
        ));
    }

    public static function getGraphData($sales_by_day)
    {
        $graph_data = array();
        foreach($sales_by_day as &$d) {
            $graph_data[] = array(
                'date' => str_replace('-', '', $d['date']),
                'sales' => $d['sales'],
                'profit' => $d['profit'],
                'loss' => $d['profit'],
            );
        }
        unset($d);
        return $graph_data;
    }

    public static function getStorefronts()
    {
        $m = new waModel();
        $sql = "SELECT DISTINCT value FROM shop_order_params WHERE name='storefront' ORDER BY value";
        return array_keys($m->query($sql)->fetchAll('value'));
    }

    public static function getTimeframeParams()
    {
        $request_options = array();

        $timeframe = waRequest::request('timeframe');
        $request_options['timeframe'] = $timeframe;
        if ($timeframe === 'all') {
            $start_date = null;
            $end_date = null;
        } else if ($timeframe == 'custom') {
            $from = waRequest::request('from', 0, 'int');
            $start_date = $from ? date('Y-m-d', $from) : null;

            $to = waRequest::request('to', 0, 'int');
            $end_date = $to ? date('Y-m-d', $to) : null;

            $from && ($request_options['from'] = $from);
            $to && ($request_options['to'] = $to);
        } else {
            if (!wa_is_int($timeframe)) {
                $timeframe = 30;
            }
            $start_date = date('Y-m-d', time() - $timeframe*24*3600);
            $end_date = null;
        }

        $group_by = waRequest::request('groupby', 'days');
        if ($group_by !== 'months') {
            $group_by = 'days';
        } else {
            $request_options['groupby'] = $group_by;
        }

        return array($start_date, $end_date, $group_by, $request_options);
    }

    public static function getMenuTypes()
    {
        return array(
            'sources' => array(
                'menu_name' => _w("All sources"),
                'header_name' => _w("Sales by source"),
            ),
            'social' => array(
                'menu_name' => _w("Social"),
                'header_name' => _w("Sales by social media"),
            ),
            'countries' => array(
                'menu_name' => _w("Countries & Regions"),
                'header_name' => _w("Sales by country and region"),
            ),
            'campaigns' => array(
                'menu_name' => _w("Campaigns"),
                'header_name' => _w("Sales by campaign"),
            ),
            'shipping' => array(
                'menu_name' => _w("Shipping"),
                'header_name' => _w("Sales by shipping option"),
            ),
            'payment' => array(
                'menu_name' => _w("Payment"),
                'header_name' => _w("Sales by payment method"),
            ),
            'coupons' => array(
                'menu_name' => _w("Coupons"),
                'header_name' => _w("Sales by coupon"),
            ),
            'landings' => array(
                'menu_name' => _w("Landings"),
                'header_name' => _w("Sales by landing page"),
            ),
        );
    }

    public static function isDisabled()
    {
        return wa('shop')->getConfig()->getOption('reports_simple');
    }

    public static function jsRedirectIfDisabled()
    {
        if (self::isDisabled()) {
            echo "<script>window.location.hash = '#/summary/';</script>";
            exit;
        }
    }

    protected function prepareTableData($type_id, &$table_data) {
        if ($type_id == 'countries') {
            $country_model = new waCountryModel();
            $country_model->preload();
            $region_model = new waRegionModel();
            $regions = array();
            foreach($table_data as &$row) {
                $row['orig_name'] = $row['name'];
                if (!$row['name']) {
                    $row['name'] = _w('(not defined)');
                } else {
                    @list($country, $region) = explode(' ', $row['name']);
                    $c = $country_model->get($country);
                    if ($c) {
                        if (!isset($regions[$country])) {
                            $regions[$country] = $region_model->getByCountry($country);
                            if (!$regions[$country]) {
                                $regions[$country] = array();
                            }
                        }
                        $rs = $regions[$country];
                        if (!$region) {
                            $region = _w('region not specified');
                        } else if (!empty($rs[$region])) {
                            $region = $rs[$region]['name'];
                        }

                        $row['name'] = $c['name'].' ('.$region.')';
                    }
                }
            }
            unset($row);
        } else if ($type_id == 'coupons') {
            $coupon_ids = array();
            foreach($table_data as $i => $row) {
                if (!$row['name']) {
                    unset($table_data[$i]);
                } else {
                    $coupon_ids[$row['name']] = $row['name'];
                }
            }
            $table_data = array_values($table_data);

            if ($coupon_ids) {
                $coupon_model = new shopCouponModel();
                $coupons = $coupon_model->getById($coupon_ids);
            }
            foreach($table_data as &$row) {
                $row['orig_name'] = $row['name'];
                if (!empty($coupons[$row['name']])) {
                    $row['name'] = $coupons[$row['name']]['code'];
                }
            }
            unset($row);
        } else if ($type_id == 'campaigns') {
            foreach($table_data as $i => $row) {
                if (!$row['name']) {
                    unset($table_data[$i]);
                }
            }
            $table_data = array_values($table_data);
        } else if ($type_id == 'sources') {
            foreach($table_data as &$row) {
                $row['orig_name'] = $row['name'];
                if (!$row['name']) {
                    $row['name'] = _w('(direct)');
                }
            }
            unset($row);
        } else if ($type_id == 'social') {
            $social_domains = wa('shop')->getConfig()->getOption('social_domains');
            foreach($table_data as &$row) {
                $row['orig_name'] = $row['name'];
                if (!empty($social_domains[$row['orig_name']]['name'])) {
                    $row['name'] = _w($social_domains[$row['orig_name']]['name']);
                }
                if (!empty($social_domains[$row['orig_name']]['icon_class'])) {
                    $row['icon_class'] = $social_domains[$row['orig_name']]['icon_class'];
                }
            }
            unset($row);
        } else {
            foreach($table_data as &$row) {
                $row['orig_name'] = $row['name'];
                if (!$row['name']) {
                    $row['name'] = _w('(not defined)');
                }
            }
            unset($row);
        }
    }
}

